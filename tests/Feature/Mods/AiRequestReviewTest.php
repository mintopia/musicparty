<?php

use App\Enums\AiReviewCriterion;
use App\Enums\AiReviewOutcome;
use App\Enums\SettingType;
use App\Models\AiReview;
use App\Models\Mod;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\PlayedSong;
use App\Models\User;
use App\Mods\AiRequestReview\AiRequestReviewMod;
use App\Mods\AiRequestReview\AiReviewBreaker;
use App\Mods\AiRequestReview\DecisionModelException;
use App\Mods\AiRequestReview\DecisionModelReviewer;
use App\Mods\AiRequestReview\HostResolver;
use App\Mods\AiRequestReview\RequestReviewAgent;
use App\Mods\PartyMods;
use App\Services\ModSettingsStore;
use App\Services\Music\Data\Track;
use App\Services\Music\Fake\FakeMusicProvider;
use App\Services\Requests\RequestMode;
use App\Services\Requests\RequestOutcome;
use App\Services\Requests\RequestRules;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Monolog\Handler\TestHandler;
use Psr\Log\NullLogger;

function aiReviewTrack(): Track
{
    return app(FakeMusicProvider::class)->getTrack('fake-track-1') ?? throw new RuntimeException('No fake track');
}

function aiReviewSetup(bool $flagRequests = true, bool $enabled = true, string $driver = 'openai-compatible'): array
{
    Artisan::call('mods:sync');
    $party = Party::factory()->create(['provider_id' => 'fake', 'allow_requests' => true, 'explicit' => true, 'flag_requests' => $flagRequests])->refresh();
    $member = PartyMember::factory()->for($party)->for(User::factory())->create();
    $store = app(ModSettingsStore::class);
    $store->set($party, AiRequestReviewMod::CODE, 'driver', $driver);
    $store->set($party, AiRequestReviewMod::CODE, 'endpoint', 'https://llm.example.test/v1');
    $store->set($party, AiRequestReviewMod::CODE, 'model', 'review-1');
    $store->set($party, AiRequestReviewMod::CODE, 'api_key', 'secret');
    if ($enabled) {
        app(PartyMods::class)->enable($party, AiRequestReviewMod::CODE);
    }

    return [$party->refresh(), $member];
}

function chatCompletion(string $verdict, string $reason = 'Fine', int $promptTokens = 11, int $completionTokens = 7): array
{
    return [
        'id' => 'chatcmpl-1',
        'object' => 'chat.completion',
        'model' => 'review-1',
        'choices' => [['index' => 0, 'finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => json_encode(['verdict' => $verdict, 'reason' => $reason])]]],
        'usage' => ['prompt_tokens' => $promptTokens, 'completion_tokens' => $completionTokens, 'total_tokens' => $promptTokens + $completionTokens],
    ];
}

function fakeResolver(array $map = []): void
{
    app()->instance(HostResolver::class, new class($map) extends HostResolver
    {
        public function __construct(protected array $map) {}

        public function resolve(string $host): array
        {
            return $this->map[$host] ?? ['93.184.216.34'];
        }
    });
}

beforeEach(function () {
    Http::preventStrayRequests();
    fakeResolver();
});

it('allows when the model allows', function () {
    RequestReviewAgent::fake([['verdict' => 'allow', 'reason' => 'Fine']])->preventStrayPrompts();
    [$party, $member] = aiReviewSetup();

    $decision = app(RequestRules::class)->check($party, $member, aiReviewTrack());

    expect($decision->outcome)->toBe(RequestOutcome::Allow);
    RequestReviewAgent::assertPromptedTimes(1);
});

it('flags with the reason when flagging is on', function () {
    RequestReviewAgent::fake([['verdict' => 'flag', 'reason' => 'Borderline lyrics']]);
    [$party, $member] = aiReviewSetup(flagRequests: true);

    $decision = app(RequestRules::class)->check($party, $member, aiReviewTrack());

    expect($decision->isFlagged())->toBeTrue()->and($decision->reason)->toBe('Borderline lyrics');
});

it('allows a flagged request when flagging is off', function () {
    RequestReviewAgent::fake([['verdict' => 'flag', 'reason' => 'Borderline lyrics']]);
    [$party, $member] = aiReviewSetup(flagRequests: false);

    expect(app(RequestRules::class)->check($party, $member, aiReviewTrack())->outcome)->toBe(RequestOutcome::Allow);
});

it('rejects with the reason shown to the requester', function () {
    RequestReviewAgent::fake([['verdict' => 'reject', 'reason' => 'Not suitable for this party']]);
    [$party, $member] = aiReviewSetup(flagRequests: false);

    $decision = app(RequestRules::class)->check($party, $member, aiReviewTrack());

    expect($decision->allowed)->toBeFalse()->and($decision->reason)->toBe('Not suitable for this party');
});

it('allows when the model errors or answers nonsense', function (Closure|array $response) {
    RequestReviewAgent::fake($response);
    [$party, $member] = aiReviewSetup();

    expect(app(RequestRules::class)->check($party, $member, aiReviewTrack())->outcome)->toBe(RequestOutcome::Allow);
})->with([
    'error' => fn () => fn () => throw new RuntimeException('timeout'),
    'unparseable' => [['I cannot decide']],
]);

it('allows without calling the model when it is not configured', function () {
    RequestReviewAgent::fake()->preventStrayPrompts();
    [$party, $member] = aiReviewSetup();
    app(ModSettingsStore::class)->set($party, AiRequestReviewMod::CODE, 'model', null);

    expect(app(RequestRules::class)->check($party, $member, aiReviewTrack())->outcome)->toBe(RequestOutcome::Allow);
    RequestReviewAgent::assertNeverPrompted();
});

it('does not call the model for a party that has not enabled the mod', function () {
    RequestReviewAgent::fake([['verdict' => 'reject', 'reason' => 'No']]);
    [$party, $member] = aiReviewSetup(enabled: false);

    expect(app(RequestRules::class)->check($party, $member, aiReviewTrack())->allowed)->toBeTrue();
    RequestReviewAgent::assertNeverPrompted();
});

it('does not call the model during annotation', function () {
    RequestReviewAgent::fake([['verdict' => 'reject', 'reason' => 'No']]);
    [$party, $member] = aiReviewSetup();

    $decision = app(RequestRules::class)->check($party, $member, aiReviewTrack(), RequestMode::Annotate);

    expect($decision->allowed)->toBeTrue();
    RequestReviewAgent::assertNeverPrompted();
});

it('registers per-party settings with an encrypted api key and a driver choice', function () {
    Artisan::call('mods:sync');
    $mod = Mod::whereCode(AiRequestReviewMod::CODE)->firstOrFail();

    expect($mod->settings()->whereCode('api_key')->sole())->encrypted->toBeTrue()->instance_only->toBeFalse()
        ->and($mod->settings()->whereCode('driver')->sole())->type->toBe(SettingType::stChoice)->instance_only->toBeFalse()
        ->options->toBe(['decision-model' => 'Decision model (Jev / TypeSafe)', 'openai-compatible' => 'OpenAI-compatible']);
});

it('presents the settings per party with the driver as a choice', function () {
    [$party] = aiReviewSetup();

    $mod = collect(app(ModSettingsStore::class)->presentFor($party))->firstWhere('code', AiRequestReviewMod::CODE);
    $settings = collect($mod['settings'])->keyBy('code');
    $driver = $settings['driver'];

    expect($settings->keys()->all())->toBe(['driver', 'endpoint', 'api_key', 'model', 'decision_endpoint', 'decision_model', 'confidence_percentage', 'include_party_context', 'timeout', 'guidelines'])
        ->and($driver['type'])->toBe('choice')
        ->and((array) $driver['options'])->toBe(['decision-model' => 'Decision model (Jev / TypeSafe)', 'openai-compatible' => 'OpenAI-compatible'])
        ->and($settings['model']['value'])->toBe('review-1');
});

it('only accepts a driver from the options', function () {
    [$party] = aiReviewSetup();
    $store = app(ModSettingsStore::class);

    $store->set($party, AiRequestReviewMod::CODE, 'driver', 'decision-model');
    expect($store->value($party, AiRequestReviewMod::CODE, 'driver'))->toBe('decision-model');

    expect(fn () => $store->set($party, AiRequestReviewMod::CODE, 'driver', 'typesafe'))->toThrow(ValidationException::class)
        ->and(fn () => $store->set($party, AiRequestReviewMod::CODE, 'driver', 'bogus'))->toThrow(ValidationException::class);
});

it('reads the driver and model from the party that is being reviewed', function () {
    Http::fake(['*' => Http::response(decisionAnswer(0.0))]);
    RequestReviewAgent::fake([['verdict' => 'allow', 'reason' => 'Fine']]);
    [$decisionParty, $decisionMember] = aiReviewSetup(driver: 'decision-model');
    [$otherParty, $otherMember] = aiReviewSetup(driver: 'openai-compatible');
    app(ModSettingsStore::class)->set($otherParty, AiRequestReviewMod::CODE, 'model', null);

    expect(app(RequestRules::class)->check($decisionParty, $decisionMember, aiReviewTrack())->allowed)->toBeFalse()
        ->and(app(RequestRules::class)->check($otherParty, $otherMember, aiReviewTrack())->allowed)->toBeTrue();
    RequestReviewAgent::assertNeverPrompted();
});

it('defaults new parties to the decision model driver', function () {
    Artisan::call('mods:sync');
    $party = Party::factory()->create()->refresh();

    expect(app(ModSettingsStore::class)->value($party, AiRequestReviewMod::CODE, 'driver'))->toBe('decision-model');
});

describe('Decision model', function () {
    function decisionAnswer(float $score, float $confidence = 0.9, array $legend = [0 => 'Against the guidelines', 1 => 'Probably against', 2 => 'Unsure', 3 => 'Probably fine', 4 => 'Clearly fine']): array
    {
        return ['answers' => ['suitability' => ['type' => 'score', 'score' => $score, 'legend' => $legend, 'probabilities' => [], 'confidence' => $confidence]], 'usage' => []];
    }

    function decisionSetup(bool $flagRequests = true): array
    {
        [$party, $member] = aiReviewSetup(flagRequests: $flagRequests, driver: 'decision-model');
        $store = app(ModSettingsStore::class);
        $store->set($party, AiRequestReviewMod::CODE, 'api_key', 'sk-secret-key');
        $store->set($party, AiRequestReviewMod::CODE, 'guidelines', 'No explicit lyrics');

        return [$party->refresh(), $member];
    }

    it('posts the score and criterion questions to the default endpoint with the key and attribution header', function () {
        Http::fake(['openrouter.ai/*' => Http::response(decisionAnswer(4))]);
        [$party, $member] = decisionSetup();

        app(RequestRules::class)->check($party, $member, aiReviewTrack());

        Http::assertSent(function (Request $request): bool {
            $question = $request['questions']['suitability'] ?? [];

            return $request->url() === 'https://openrouter.ai/api/alpha/decisions'
                && $request->hasHeader('Authorization', 'Bearer sk-secret-key')
                && $request->hasHeader('X-Title', 'Music Party')
                && $request['model'] === 'typesafe/jev-1.13'
                && $question['type'] === 'score'
                && count($question['criteria']) === 5
                && str_contains($question['instructions'], 'No explicit lyrics')
                && isset($request['state']['request']['title'])
                && ! str_contains($request->body(), 'sk-secret-key')
                && array_keys($request['questions']) === ['suitability', 'criterion'];
        });
    });

    it('uses a configured endpoint and model', function () {
        Http::fake(['api.typesafe.ai/*' => Http::response(decisionAnswer(4))]);
        [$party, $member] = decisionSetup();
        $store = app(ModSettingsStore::class);
        $store->set($party, AiRequestReviewMod::CODE, 'decision_endpoint', 'https://api.typesafe.ai/v1/systemone');
        $store->set($party, AiRequestReviewMod::CODE, 'decision_model', 'jev-latest');

        app(RequestRules::class)->check($party, $member, aiReviewTrack());

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.typesafe.ai/v1/systemone'
            && $request->hasHeader('Authorization', 'Bearer sk-secret-key')
            && array_keys($request->data()) === ['model', 'state', 'questions']
            && $request['model'] === 'jev-latest'
            && is_array($request['state'])
            && $request['questions']['suitability']['type'] === 'score');
    });

    function criterionAnswer(float $score, mixed $criterion): array
    {
        return array_replace_recursive(decisionAnswer($score), ['answers' => ['criterion' => $criterion]]);
    }

    it('asks a criterion choice question with guidelines and an untrusted data warning', function () {
        Http::fake(['*' => Http::response(decisionAnswer(4))]);
        [$party, $member] = decisionSetup();

        app(RequestRules::class)->check($party, $member, aiReviewTrack());

        Http::assertSent(function (Request $request): bool {
            $question = $request['questions']['criterion'] ?? [];

            return $question['type'] === 'choice'
                && array_keys($question['criteria']) === ['none', 'guidelines', 'explicit', 'vibe', 'troll', 'other']
                && str_contains($question['instructions'], 'No explicit lyrics')
                && str_contains($question['instructions'], 'untrusted data');
        });
    });

    it('records the criterion and prefixes the reason on a flag or reject', function (float $score, RequestOutcome $expected, string $reason) {
        Http::fake(['*' => Http::response(criterionAnswer($score, ['type' => 'choice', 'choice' => 'vibe', 'probabilities' => ['none' => 0.1, 'vibe' => 0.7, 'other' => 0.2], 'confidence' => 0.8]))]);
        [$party, $member] = decisionSetup();

        $decision = app(RequestRules::class)->check($party, $member, aiReviewTrack());

        $review = AiReview::sole();
        expect($decision->outcome)->toBe($expected)->and($decision->reason)->toBe($reason)
            ->and($review->criterion)->toBe(AiReviewCriterion::Vibe)
            ->and($review->criterion_confidence)->toBe(0.7);
    })->with([
        'reject' => [0.2, RequestOutcome::Reject, 'Vibe: Against the guidelines'],
        'flag' => [1.4, RequestOutcome::Flag, 'Vibe: Probably against'],
    ]);

    it('keeps the plain reason for the none criterion and does not change an allow', function () {
        Http::fake(['*' => Http::response(criterionAnswer(1.4, ['type' => 'choice', 'choice' => 'none', 'probabilities' => ['none' => 0.9], 'confidence' => 0.9]))]);
        [$party, $member] = decisionSetup();

        $decision = app(RequestRules::class)->check($party, $member, aiReviewTrack());

        expect($decision->reason)->toBe('Probably against')->and(AiReview::sole()->criterion)->toBe(AiReviewCriterion::None);
    });

    it('allows a high score whatever criterion was chosen', function () {
        Http::fake(['*' => Http::response(criterionAnswer(4, ['type' => 'choice', 'choice' => 'troll', 'probabilities' => ['troll' => 0.9], 'confidence' => 0.9]))]);
        [$party, $member] = decisionSetup();

        expect(app(RequestRules::class)->check($party, $member, aiReviewTrack())->outcome)->toBe(RequestOutcome::Allow);
    });

    it('ignores a refused, missing, unknown or non-choice criterion without changing the decision', function (?array $criterion) {
        $response = decisionAnswer(1.4);
        if ($criterion !== null) {
            $response['answers']['criterion'] = $criterion;
        }
        Http::fake(['*' => Http::response($response)]);
        [$party, $member] = decisionSetup();

        $decision = app(RequestRules::class)->check($party, $member, aiReviewTrack());

        $review = AiReview::sole();
        expect($decision->outcome)->toBe(RequestOutcome::Flag)->and($decision->reason)->toBe('Probably against')
            ->and($review->criterion)->toBeNull()->and($review->criterion_confidence)->toBeNull();
    })->with([
        'refusal' => [['type' => 'refusal']],
        'missing' => [null],
        'unknown key' => [['type' => 'choice', 'choice' => 'bogus', 'probabilities' => [], 'confidence' => 0.9]],
        'non-choice' => [['type' => 'score', 'score' => 2.0, 'confidence' => 0.9]],
    ]);

    it('maps score bands to a decision with the legend as the reason', function (float $score, RequestOutcome $expected, ?string $reason) {
        Http::fake(['*' => Http::response(decisionAnswer($score))]);
        [$party, $member] = decisionSetup();

        $decision = app(RequestRules::class)->check($party, $member, aiReviewTrack());

        expect($decision->outcome)->toBe($expected)->and($decision->reason)->toBe($reason);
    })->with([
        'worst' => [0.2, RequestOutcome::Reject, 'Against the guidelines'],
        'probably against' => [1.4, RequestOutcome::Flag, 'Probably against'],
        'unsure' => [2.0, RequestOutcome::Flag, 'Unsure'],
        'probably fine' => [3.2, RequestOutcome::Allow, null],
        'best' => [4.0, RequestOutcome::Allow, null],
    ]);

    it('acts on the rating only at or above the confidence percentage', function (float $confidence, RequestOutcome $expected) {
        Http::fake(['*' => Http::response(decisionAnswer(0.0, $confidence))]);
        [$party, $member] = decisionSetup();
        app(ModSettingsStore::class)->set($party, AiRequestReviewMod::CODE, 'confidence_percentage', 60);

        expect(app(RequestRules::class)->check($party->refresh(), $member, aiReviewTrack())->outcome)->toBe($expected);
    })->with([
        'below' => [0.55, RequestOutcome::Allow],
        'exactly' => [0.6, RequestOutcome::Reject],
        'above' => [0.65, RequestOutcome::Reject],
    ]);

    it('allows a reject score when the confidence is below the default percentage', function () {
        Http::fake(['*' => Http::response(decisionAnswer(0.0, 0.1))]);
        [$party, $member] = decisionSetup();

        expect(app(RequestRules::class)->check($party, $member, aiReviewTrack())->outcome)->toBe(RequestOutcome::Allow);
    });

    it('allows on timeouts, http errors and malformed answers', function (Closure $fake) {
        Http::fake(['*' => $fake]);
        [$party, $member] = decisionSetup();
        Log::spy();
        Log::shouldReceive('channel')->andReturn(new NullLogger);

        expect(app(RequestRules::class)->check($party, $member, aiReviewTrack())->outcome)->toBe(RequestOutcome::Allow);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => ! str_contains($message, 'sk-secret-key'))->once();
    })->with([
        'timeout' => fn () => fn () => throw new ConnectionException('cURL error 28 for sk-secret-key'),
        'server error' => fn () => fn () => Http::response('boom sk-secret-key', 500),
        'no answer' => fn () => fn () => Http::response(['answers' => []]),
        'non numeric' => fn () => fn () => Http::response(['answers' => ['suitability' => ['score' => 'high']]]),
    ]);

    it('does not call out without an api key', function () {
        [$party, $member] = decisionSetup();
        $apiKeyId = app(ModSettingsStore::class)->find($party, AiRequestReviewMod::CODE, 'api_key')->mod_setting_id;
        DB::table('party_mod_settings')->where('mod_setting_id', $apiKeyId)->delete();
        app(ModSettingsStore::class)->forget($party);

        expect(app(RequestRules::class)->check($party, $member, aiReviewTrack())->outcome)->toBe(RequestOutcome::Allow);
        Http::assertNothingSent();
    });

    it('refuses internal endpoints without sending the key', function (string $endpoint) {
        $reviewer = app(DecisionModelReviewer::class);

        expect(fn () => $reviewer->review($endpoint, 'sk-secret-key', 'm', null, [], 5, 0.5))
            ->toThrow(DecisionModelException::class);
        Http::assertNothingSent();
    })->with(['ftp' => 'ftp://openrouter.ai/x', 'localhost' => 'https://localhost/x', 'loopback' => 'https://127.0.0.1/x', 'private' => 'https://10.0.0.5/x', 'metadata' => 'https://169.254.169.254/x']);

    it('validates the decision endpoint as a url', function () {
        [$party] = decisionSetup();
        $store = app(ModSettingsStore::class);

        $store->set($party, AiRequestReviewMod::CODE, 'decision_endpoint', 'http://example.test/decisions');
        expect(fn () => $store->set($party, AiRequestReviewMod::CODE, 'decision_endpoint', 'not a url'))->toThrow(ValidationException::class)
            ->and(fn () => $store->set($party, AiRequestReviewMod::CODE, 'confidence_percentage', 150))->toThrow(ValidationException::class)
            ->and(fn () => $store->set($party, AiRequestReviewMod::CODE, 'confidence_percentage', -1))->toThrow(ValidationException::class);
        $store->set($party, AiRequestReviewMod::CODE, 'confidence_percentage', 0);
        $store->set($party, AiRequestReviewMod::CODE, 'confidence_percentage', 100);
        expect($store->value($party, AiRequestReviewMod::CODE, 'confidence_percentage'))->toBe(100);
        $store->set($party, AiRequestReviewMod::CODE, 'decision_endpoint', 'https://example.test/decisions');
        expect($store->value($party, AiRequestReviewMod::CODE, 'decision_endpoint'))->toBe('https://example.test/decisions');
    });

    it('refuses a hostname that resolves to the metadata address for the decision driver, sending no key', function () {
        fakeResolver(['sneaky.example.test' => ['169.254.169.254']]);
        [$party, $member] = decisionSetup();
        app(ModSettingsStore::class)->set($party, AiRequestReviewMod::CODE, 'decision_endpoint', 'https://sneaky.example.test/decisions');

        expect(app(RequestRules::class)->check($party, $member, aiReviewTrack())->outcome)->toBe(RequestOutcome::Allow);
        Http::assertNothingSent();
        expect(AiReview::query()->latest('id')->first()->error_message)->toContain('internal address');
    });

    it('refuses a hostname that resolves to an internal address for the openai-compatible driver', function () {
        fakeResolver(['llm.example.test' => ['10.1.2.3']]);
        [$party, $member] = aiReviewSetup();
        Http::fake();

        expect(app(RequestRules::class)->check($party, $member, aiReviewTrack())->outcome)->toBe(RequestOutcome::Allow);
        Http::assertNothingSent();
    });

    it('lets a site admin allow internal endpoints but not a party owner', function () {
        fakeResolver(['sneaky.example.test' => ['10.0.0.9']]);
        Http::fake(['sneaky.example.test/*' => Http::response(decisionAnswer(0.2))]);
        [$party, $member] = decisionSetup();
        $store = app(ModSettingsStore::class);
        $store->set($party, AiRequestReviewMod::CODE, 'decision_endpoint', 'https://sneaky.example.test/decisions');

        expect(fn () => $store->set($party, AiRequestReviewMod::CODE, 'allow_internal_endpoints', true))->toThrow(InvalidArgumentException::class);
        Http::assertNothingSent();

        $store->setInstance(AiRequestReviewMod::CODE, 'allow_internal_endpoints', true);

        expect(app(RequestRules::class)->check($party, $member, aiReviewTrack())->outcome)->toBe(RequestOutcome::Reject);
        Http::assertSentCount(1);
    });

    it('caps the timeout at 20 seconds', function () {
        Http::fake(['openrouter.ai/*' => Http::response(decisionAnswer(4))]);
        [$party, $member] = decisionSetup();
        $mod = DB::table('mod_settings')->where('code', 'timeout')->first();
        DB::table('party_mod_settings')->insert(['party_id' => $party->id, 'mod_setting_id' => $mod->id, 'value' => '120', 'created_at' => now(), 'updated_at' => now()]);
        app(ModSettingsStore::class)->forget($party);
        $handler = aiReviewLogHandler();

        app(RequestRules::class)->check($party, $member, aiReviewTrack());

        expect(aiReviewLogged($handler, 'ai-review.request')['timeout'])->toBe(20);
    });

    it('opens the breaker after repeated failures and allows without calling the model', function () {
        Http::fake(['openrouter.ai/*' => Http::response('boom', 500)]);
        [$party, $member] = decisionSetup();

        foreach (range(1, AiReviewBreaker::FAILURE_THRESHOLD) as $i) {
            app(RequestRules::class)->check($party, $member, aiReviewTrack());
        }
        Http::assertSentCount(AiReviewBreaker::FAILURE_THRESHOLD);

        Http::fake(['openrouter.ai/*' => Http::response(decisionAnswer(0.1))]);
        $decision = app(RequestRules::class)->check($party, $member, aiReviewTrack());

        expect($decision->outcome)->toBe(RequestOutcome::Allow);
        Http::assertNothingSent();
        expect(app(AiReviewBreaker::class)->isOpen($party))->toBeTrue();

        $this->travel(AiReviewBreaker::OPEN_SECONDS + 1)->seconds();
        expect(app(AiReviewBreaker::class)->isOpen($party))->toBeFalse();
    });

    it('reuses the cached verdict for the same track in the same party', function () {
        Http::fake(['openrouter.ai/*' => Http::response(decisionAnswer(0.2))]);
        [$party, $member] = decisionSetup();

        $first = app(RequestRules::class)->check($party, $member, aiReviewTrack());
        $second = app(RequestRules::class)->check($party, $member, aiReviewTrack());

        expect($first->outcome)->toBe(RequestOutcome::Reject)
            ->and($second->outcome)->toBe(RequestOutcome::Reject);
        Http::assertSentCount(1);
    });

    it('stores the api key encrypted and never presents it', function () {
        [$party] = decisionSetup();

        $raw = (string) DB::table('party_mod_settings')->pluck('value')->implode('|');
        $mod = collect(app(ModSettingsStore::class)->presentFor($party))->firstWhere('code', AiRequestReviewMod::CODE);
        $apiKey = collect($mod['settings'])->firstWhere('code', 'api_key');

        expect($raw)->not->toContain('sk-secret-key')
            ->and(json_encode($mod))->not->toContain('sk-secret-key')
            ->and($apiKey['value'])->toBeNull()
            ->and($apiKey['has_value'])->toBeTrue();
    });
});

describe('Debug logging', function () {
    function aiReviewLogHandler(): TestHandler
    {
        $handler = new TestHandler;
        config(['logging.channels.ai-review' => ['driver' => 'monolog', 'handler' => TestHandler::class, 'level' => 'debug']]);
        Log::forgetChannel('ai-review');
        $logger = Log::channel('ai-review')->getLogger();
        $logger->setHandlers([$handler]);

        return $handler;
    }

    function aiReviewLogged(TestHandler $handler, string $event): array
    {
        $records = array_values(array_filter($handler->getRecords(), fn ($record): bool => $record->message === $event));

        return $records === [] ? [] : $records[0]->context;
    }

    it('logs the chat prompt, response and outcome with the party and track', function () {
        RequestReviewAgent::fake([['verdict' => 'reject', 'reason' => 'Not for this party']]);
        [$party, $member] = aiReviewSetup(flagRequests: false);
        app(ModSettingsStore::class)->set($party, AiRequestReviewMod::CODE, 'guidelines', 'No explicit lyrics');
        $handler = aiReviewLogHandler();

        app(RequestRules::class)->check($party->refresh(), $member, aiReviewTrack());

        $request = aiReviewLogged($handler, 'ai-review.request');
        $outcome = aiReviewLogged($handler, 'ai-review.outcome');
        expect($request)->toMatchArray(['party_id' => $party->id, 'party_code' => $party->code, 'driver' => 'openai-compatible', 'model' => 'review-1', 'track_id' => aiReviewTrack()->id])
            ->and($request['system'])->toContain('Party guidelines: No explicit lyrics')
            ->and($request['user'])->toContain('"title"')
            ->and(aiReviewLogged($handler, 'ai-review.response'))->toMatchArray(['verdict' => 'reject', 'reason' => 'Not for this party'])->toHaveKey('latency_ms')
            ->and($outcome)->toMatchArray(['outcome' => 'reject', 'why' => 'verdict:reject']);
    });

    it('logs the decision body, raw response, band and endpoint host without the key', function (float $score, float $confidence, string $band, string $outcome) {
        Http::fake(['openrouter.ai/*' => Http::response(decisionAnswer($score, $confidence))]);
        [$party, $member] = decisionSetup();
        $handler = aiReviewLogHandler();

        app(RequestRules::class)->check($party, $member, aiReviewTrack());

        $request = aiReviewLogged($handler, 'ai-review.request');
        $decision = aiReviewLogged($handler, 'ai-review.decision');
        expect($request)->toMatchArray(['driver' => 'decision-model', 'endpoint_host' => 'openrouter.ai', 'model' => 'typesafe/jev-1.13'])
            ->and($request['body']['questions']['suitability']['instructions'])->toContain('No explicit lyrics')
            ->and($request['headers']['Authorization'])->toBe('Bearer [redacted]')
            ->and(aiReviewLogged($handler, 'ai-review.response'))->toHaveKeys(['latency_ms', 'status', 'body'])
            ->and($decision)->toMatchArray(['score' => $score, 'confidence' => $confidence, 'band' => $band, 'outcome' => $outcome])
            ->and(aiReviewLogged($handler, 'ai-review.outcome'))->toMatchArray(['outcome' => $outcome, 'why' => 'decision_model']);
        expect(json_encode($handler->getRecords()))->not->toContain('sk-secret-key');
    })->with([
        'reject band' => [0.2, 0.9, 'reject', 'reject'],
        'flag band' => [1.4, 0.9, 'flag', 'flag'],
        'allow band' => [4.0, 0.9, 'allow', 'allow'],
        'low confidence' => [4.0, 0.1, 'low_confidence', 'allow'],
    ]);

    it('logs fail-open errors with a sanitized message and never the key', function (Closure $fake) {
        Http::fake(['*' => $fake]);
        [$party, $member] = decisionSetup();
        $handler = aiReviewLogHandler();

        expect(app(RequestRules::class)->check($party, $member, aiReviewTrack())->outcome)->toBe(RequestOutcome::Allow);

        $error = aiReviewLogged($handler, 'ai-review.error');
        expect($error)->toHaveKeys(['error_class', 'error_message', 'latency_ms'])
            ->and(aiReviewLogged($handler, 'ai-review.outcome'))->toMatchArray(['outcome' => 'allow', 'why' => 'fail_open_on_error'])
            ->and(json_encode($handler->getRecords()))->not->toContain('sk-secret-key');
    })->with([
        'timeout' => fn () => fn () => throw new ConnectionException('cURL error 28 for Bearer sk-secret-key'),
        'server error' => fn () => fn () => Http::response('boom sk-secret-key', 500),
    ]);

    it('never logs the key on the chat path when the provider errors with it', function () {
        RequestReviewAgent::fake(fn () => throw new RuntimeException('401 for Bearer secret'));
        [$party, $member] = aiReviewSetup();
        $handler = aiReviewLogHandler();

        app(RequestRules::class)->check($party, $member, aiReviewTrack());

        expect(aiReviewLogged($handler, 'ai-review.error')['error_message'])->toBe('401 for Bearer [redacted]')
            ->and(json_encode($handler->getRecords()))->not->toContain('"secret"')->not->toContain('Bearer secret');
    });
});

describe('Party context', function () {
    it('sends the party context to the decision model and logs it', function () {
        Http::fake(['*' => Http::response(decisionAnswer(4))]);
        [$party, $member] = decisionSetup();
        $played = PlayedSong::factory()->for($party)->create(['rating' => 2]);
        $handler = aiReviewLogHandler();

        app(RequestRules::class)->check($party, $member, aiReviewTrack());

        Http::assertSent(fn (Request $request): bool => is_array($request['state']) && str_contains(json_encode($request['state']['party']), $played->song->name));
        expect(json_encode(aiReviewLogged($handler, 'ai-review.request')['body']))->toContain('most_upvoted_requests')
            ->and(aiReviewLogged($handler, 'ai-review.outcome')['party_context_included'])->toBeTrue();
    });

    it('sends the party context in the chat user message and logs it', function () {
        RequestReviewAgent::fake([['verdict' => 'allow', 'reason' => 'Fine']]);
        [$party, $member] = aiReviewSetup();
        $played = PlayedSong::factory()->for($party)->create();
        $handler = aiReviewLogHandler();

        app(RequestRules::class)->check($party, $member, aiReviewTrack());

        expect(aiReviewLogged($handler, 'ai-review.request')['user'])->toContain('party_context')->toContain($played->song->name);
    });

    it('sends recent history and top tracks in the chat user message over the wire', function () {
        Http::fake(['llm.example.test/*' => Http::response(chatCompletion('allow'))]);
        [$party, $member] = aiReviewSetup();
        $played = PlayedSong::factory()->for($party)->create(['rating' => 3]);

        app(RequestRules::class)->check($party, $member, aiReviewTrack());

        Http::assertSent(function (Request $request) use ($played): bool {
            $messages = $request['messages'];
            $user = json_decode($messages[1]['content'], true);

            return $messages[1]['role'] === 'user'
                && str_contains($user['party_context'], 'Most recently played')
                && str_contains($user['party_context'], 'Highest rated by guests')
                && str_contains($user['party_context'], $played->song->name)
                && ! str_contains($messages[0]['content'], $played->song->name);
        });
    });

    it('sends recent history and top tracks in the decision model state over the wire', function () {
        Http::fake(['*' => Http::response(decisionAnswer(4))]);
        [$party, $member] = decisionSetup();
        $played = PlayedSong::factory()->for($party)->create(['rating' => 3]);

        app(RequestRules::class)->check($party, $member, aiReviewTrack());

        Http::assertSent(function (Request $request) use ($played): bool {
            $state = $request['state'];

            return is_array($state)
                && is_array($state['request'])
                && isset($state['request']['title'])
                && array_keys($state['party']) === ['stats', 'recent', 'top_rated', 'downvoted', 'most_upvoted_requests', 'most_downvoted_requests', 'artists']
                && str_contains(json_encode($state['party']['top_rated']), $played->song->name)
                && ! str_contains($request['questions']['suitability']['instructions'], $played->song->name);
        });
    });

    it('leaves the party context out when the setting is off', function () {
        Http::fake(['*' => Http::response(decisionAnswer(4))]);
        [$party, $member] = decisionSetup();
        PlayedSong::factory()->for($party)->create();
        app(ModSettingsStore::class)->set($party, AiRequestReviewMod::CODE, 'include_party_context', false);

        app(RequestRules::class)->check($party->refresh(), $member, aiReviewTrack());

        Http::assertSent(fn (Request $request): bool => is_array($request['state']) && array_keys($request['state']) === ['request']);
    });

    it('defaults the setting on', function () {
        $definition = collect(new AiRequestReviewMod()->settings())->first(fn ($setting): bool => $setting->code === 'include_party_context');

        expect($definition->type)->toBe(SettingType::stBoolean)->and($definition->default)->toBeTrue();
    });
});

describe('History', function () {
    it('records a chat review with the messages, response and token usage', function () {
        Http::fake(['llm.example.test/*' => Http::response(chatCompletion('reject', 'Not for this party', 21, 9))]);
        [$party, $member] = aiReviewSetup(flagRequests: false);
        app(ModSettingsStore::class)->set($party, AiRequestReviewMod::CODE, 'guidelines', 'No explicit lyrics');

        app(RequestRules::class)->check($party->refresh(), $member, aiReviewTrack());

        $review = AiReview::sole();
        expect($review)
            ->party_id->toBe($party->id)
            ->user_id->toBe($member->user_id)
            ->party_member_id->toBe($member->id)
            ->track_provider->toBe('fake')
            ->track_id->toBe(aiReviewTrack()->id)
            ->track_title->toBe(aiReviewTrack()->name)
            ->driver->toBe('openai-compatible')
            ->model->toBe('review-1')
            ->endpoint_host->toBe('llm.example.test')
            ->outcome->toBe(AiReviewOutcome::Reject)
            ->reason->toBe('Not for this party')
            ->prompt_tokens->toBe(21)
            ->completion_tokens->toBe(9)
            ->total_tokens->toBe(30)
            ->error_class->toBeNull()
            ->and($review->latency_ms)->toBeInt()
            ->and($review->request['messages'][0]['content'])->toContain('Party guidelines: No explicit lyrics')
            ->and($review->request['messages'][1]['content'])->toContain('"title"')
            ->and($review->response)->toMatchArray(['verdict' => 'reject', 'reason' => 'Not for this party']);
    });

    it('records a decision model review with the exact body, raw response, score and usage', function () {
        $answer = decisionAnswer(1.4, 0.8);
        $answer['usage'] = ['prompt_tokens' => 300, 'completion_tokens' => 12, 'total_tokens' => 312];
        Http::fake(['openrouter.ai/*' => Http::response($answer)]);
        [$party, $member] = decisionSetup();
        $sent = null;

        app(RequestRules::class)->check($party, $member, aiReviewTrack());
        Http::assertSent(function (Request $request) use (&$sent): bool {
            $sent = $request->data();

            return true;
        });

        $review = AiReview::sole();
        expect($review)
            ->driver->toBe('decision-model')
            ->model->toBe('typesafe/jev-1.13')
            ->endpoint_host->toBe('openrouter.ai')
            ->outcome->toBe(AiReviewOutcome::Flag)
            ->reason->toBe('Probably against')
            ->score->toBe(1.4)
            ->confidence->toBe(0.8)
            ->prompt_tokens->toBe(300)
            ->completion_tokens->toBe(12)
            ->total_tokens->toBe(312)
            ->and($review->request)->toBe($sent)
            ->and($review->response['answers']['suitability']['score'])->toBe(1.4);
    });

    it('records each outcome the model can produce', function (float $score, float $confidence, AiReviewOutcome $expected) {
        Http::fake(['*' => Http::response(decisionAnswer($score, $confidence))]);
        [$party, $member] = decisionSetup();

        app(RequestRules::class)->check($party, $member, aiReviewTrack());

        expect(AiReview::sole()->outcome)->toBe($expected);
    })->with([
        'allow' => [4.0, 0.9, AiReviewOutcome::Allow],
        'flag' => [1.4, 0.9, AiReviewOutcome::Flag],
        'reject' => [0.2, 0.9, AiReviewOutcome::Reject],
        'low confidence is allowed' => [1.4, 0.1, AiReviewOutcome::Allow],
    ]);

    it('records the review as flagged even when the party has flagging off', function () {
        Http::fake(['*' => Http::response(decisionAnswer(1.4))]);
        [$party, $member] = decisionSetup(flagRequests: false);

        expect(app(RequestRules::class)->check($party, $member, aiReviewTrack())->outcome)->toBe(RequestOutcome::Allow);
        expect(AiReview::sole()->outcome)->toBe(AiReviewOutcome::Flag);
    });

    it('records fail-open errors with a sanitised message and the raw error body', function (Closure $fake, string $class) {
        Http::fake(['*' => $fake]);
        [$party, $member] = decisionSetup();

        expect(app(RequestRules::class)->check($party, $member, aiReviewTrack())->outcome)->toBe(RequestOutcome::Allow);

        $review = AiReview::sole();
        expect($review)
            ->outcome->toBe(AiReviewOutcome::Error)
            ->error_class->toBe($class)
            ->and($review->error_message)->not->toContain('sk-secret-key')->not->toBeEmpty()
            ->and(json_encode($review->getAttributes()))->not->toContain('sk-secret-key');
    })->with([
        'timeout' => [fn () => fn () => throw new ConnectionException('cURL error 28 for Bearer sk-secret-key'), DecisionModelException::class],
        'server error' => [fn () => fn () => Http::response('boom sk-secret-key', 500), DecisionModelException::class],
    ]);

    it('records the raw error response body when the server fails', function () {
        Http::fake(['*' => Http::response('boom sk-secret-key', 500)]);
        [$party, $member] = decisionSetup();

        app(RequestRules::class)->check($party, $member, aiReviewTrack());

        $response = AiReview::sole()->response;
        expect($response['status'])->toBe(500)->and($response['body'])->toContain('boom')->not->toContain('sk-secret-key');
    });

    it('records a chat provider error', function () {
        RequestReviewAgent::fake(fn () => throw new RuntimeException('401 for Bearer secret'));
        [$party, $member] = aiReviewSetup();

        app(RequestRules::class)->check($party, $member, aiReviewTrack());

        $review = AiReview::sole();
        expect($review)->outcome->toBe(AiReviewOutcome::Error)->error_class->toBe(RuntimeException::class)
            ->error_message->toBe('401 for Bearer [redacted]')
            ->and($review->request['messages'])->toHaveCount(2)
            ->and($review->response)->toBeNull();
    });

    it('never stores the api key or an authorization header in the request or response', function (string $driver) {
        Http::fake([
            'openrouter.ai/*' => Http::response(decisionAnswer(4) + ['echo' => 'Bearer sk-secret-key']),
            'llm.example.test/*' => Http::response(chatCompletion('allow')),
        ]);
        [$party, $member] = aiReviewSetup(driver: $driver);
        $store = app(ModSettingsStore::class);
        $store->set($party, AiRequestReviewMod::CODE, 'api_key', 'sk-secret-key');
        $store->set($party, AiRequestReviewMod::CODE, 'guidelines', 'Use key sk-secret-key never');

        app(RequestRules::class)->check($party->refresh(), $member, aiReviewTrack());

        $stored = json_encode(AiReview::sole()->getAttributes());
        expect($stored)->not->toContain('sk-secret-key')->not->toContain('Authorization')->not->toContain('Bearer sk');
    })->with(['decision-model', 'openai-compatible']);

    it('includes the party context that was sent in the stored request', function () {
        Http::fake(['*' => Http::response(decisionAnswer(4))]);
        [$party, $member] = decisionSetup();
        $played = PlayedSong::factory()->for($party)->create(['rating' => 2]);

        app(RequestRules::class)->check($party, $member, aiReviewTrack());

        expect(AiReview::sole()->request['state']['party']['recent'])->not->toBeEmpty()
            ->and(json_encode(AiReview::sole()->request['state']))->toContain($played->song->name);
    });

    it('does not record when the review is skipped', function () {
        RequestReviewAgent::fake()->preventStrayPrompts();
        [$party, $member] = aiReviewSetup();
        app(ModSettingsStore::class)->set($party, AiRequestReviewMod::CODE, 'model', null);

        app(RequestRules::class)->check($party, $member, aiReviewTrack());
        app(RequestRules::class)->check($party, $member, aiReviewTrack(), RequestMode::Annotate);

        expect(AiReview::count())->toBe(0);
    });

    it('does not break the request when the history cannot be saved', function () {
        Http::fake(['*' => Http::response(decisionAnswer(4))]);
        [$party, $member] = decisionSetup();
        AiReview::creating(fn () => throw new RuntimeException('disk full'));

        $outcome = app(RequestRules::class)->check($party, $member, aiReviewTrack())->outcome;
        AiReview::flushEventListeners();

        expect($outcome)->toBe(RequestOutcome::Allow)->and(AiReview::count())->toBe(0);
    });

    it('prunes reviews older than the retention period', function () {
        config(['musicparty.ai_review_retention_days' => 30]);
        $old = AiReview::factory()->create(['created_at' => now()->subDays(31)]);
        $recent = AiReview::factory()->create(['created_at' => now()->subDays(29)]);

        Artisan::call('model:prune', ['--model' => [AiReview::class]]);

        expect(AiReview::pluck('id')->all())->toBe([$recent->id])->and(AiReview::find($old->id))->toBeNull();
    });
});
