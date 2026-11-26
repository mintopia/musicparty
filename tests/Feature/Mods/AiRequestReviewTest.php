<?php

use App\Enums\SettingType;
use App\Models\Mod;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\PlayedSong;
use App\Models\User;
use App\Mods\AiRequestReview\AiRequestReviewMod;
use App\Mods\AiRequestReview\DecisionModelException;
use App\Mods\AiRequestReview\DecisionModelReviewer;
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

beforeEach(function () {
    Http::preventStrayRequests();
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

    expect($settings->keys()->all())->toBe(['driver', 'endpoint', 'api_key', 'model', 'decision_endpoint', 'decision_model', 'confidence_threshold', 'include_party_context', 'timeout', 'guidelines'])
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

    it('posts one score question to the default endpoint with the key and attribution header', function () {
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
                && str_contains($request['state'], '"title"')
                && ! str_contains($request->body(), 'sk-secret-key')
                && count($request['questions']) === 1;
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
            && is_string($request['state'])
            && $request['questions']['suitability']['type'] === 'score');
    });

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

    it('flags a confident-looking score that is below the confidence threshold', function (float $confidence, RequestOutcome $expected) {
        Http::fake(['*' => Http::response(decisionAnswer(4.0, $confidence))]);
        [$party, $member] = decisionSetup();
        app(ModSettingsStore::class)->set($party, AiRequestReviewMod::CODE, 'confidence_threshold', 0.6);

        expect(app(RequestRules::class)->check($party->refresh(), $member, aiReviewTrack())->outcome)->toBe($expected);
    })->with([
        'low' => [0.3, RequestOutcome::Flag],
        'enough' => [0.6, RequestOutcome::Allow],
    ]);

    it('flags low confidence even on a reject score so a human decides', function () {
        Http::fake(['*' => Http::response(decisionAnswer(0.0, 0.1))]);
        [$party, $member] = decisionSetup();

        expect(app(RequestRules::class)->check($party, $member, aiReviewTrack())->isFlagged())->toBeTrue();
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

    it('refuses internal or non-https endpoints without sending the key', function (string $endpoint) {
        $reviewer = app(DecisionModelReviewer::class);

        expect(fn () => $reviewer->review($endpoint, 'sk-secret-key', 'm', null, [], 5, 0.5))
            ->toThrow(DecisionModelException::class);
        Http::assertNothingSent();
    })->with(['http' => 'http://openrouter.ai/x', 'localhost' => 'https://localhost/x', 'loopback' => 'https://127.0.0.1/x', 'private' => 'https://10.0.0.5/x', 'metadata' => 'https://169.254.169.254/x']);

    it('validates the decision endpoint as an https url', function () {
        [$party] = decisionSetup();
        $store = app(ModSettingsStore::class);

        expect(fn () => $store->set($party, AiRequestReviewMod::CODE, 'decision_endpoint', 'http://example.test/decisions'))->toThrow(ValidationException::class)
            ->and(fn () => $store->set($party, AiRequestReviewMod::CODE, 'decision_endpoint', 'not a url'))->toThrow(ValidationException::class)
            ->and(fn () => $store->set($party, AiRequestReviewMod::CODE, 'confidence_threshold', 1.5))->toThrow(ValidationException::class);
        $store->set($party, AiRequestReviewMod::CODE, 'decision_endpoint', 'https://example.test/decisions');
        expect($store->value($party, AiRequestReviewMod::CODE, 'decision_endpoint'))->toBe('https://example.test/decisions');
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
        'low confidence' => [4.0, 0.1, 'low_confidence', 'flag'],
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

        Http::assertSent(fn (Request $request): bool => str_contains($request['state'], 'party_context') && str_contains($request['state'], $played->song->name));
        expect(json_encode(aiReviewLogged($handler, 'ai-review.request')['body']))->toContain('party_context')
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
            $state = json_decode($request['state'], true);

            return str_contains($state['party_context'], 'Most recently played')
                && str_contains($state['party_context'], 'Highest rated by guests')
                && str_contains($state['party_context'], $played->song->name)
                && ! str_contains($request['questions']['suitability']['instructions'], $played->song->name);
        });
    });

    it('leaves the party context out when the setting is off', function () {
        Http::fake(['*' => Http::response(decisionAnswer(4))]);
        [$party, $member] = decisionSetup();
        PlayedSong::factory()->for($party)->create();
        app(ModSettingsStore::class)->set($party, AiRequestReviewMod::CODE, 'include_party_context', false);

        app(RequestRules::class)->check($party->refresh(), $member, aiReviewTrack());

        Http::assertSent(fn (Request $request): bool => ! str_contains($request['state'], 'party_context'));
    });

    it('defaults the setting on', function () {
        $definition = collect(new AiRequestReviewMod()->settings())->first(fn ($setting): bool => $setting->code === 'include_party_context');

        expect($definition->type)->toBe(SettingType::stBoolean)->and($definition->default)->toBeTrue();
    });
});
