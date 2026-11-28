<?php

use App\Enums\AiReviewOutcome;
use App\Models\AiReview;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\Role;
use App\Models\User;
use App\Mods\AiRequestReview\AiRequestReviewMod;
use App\Mods\AiRequestReview\PinnedClientOptions;
use App\Mods\AiRequestReview\AiReviewBreaker;
use App\Mods\AiRequestReview\HostResolver;
use App\Mods\PartyMods;
use App\Services\ModSettingsStore;
use App\Services\ModSettingsUpdater;
use App\Services\Music\Fake\FakeMusicProvider;
use App\Services\Requests\RequestOutcome;
use App\Services\Requests\RequestRules;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

function safetyResolver(array $map = []): void
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

function safetyChat(string $verdict): array
{
    return [
        'id' => 'c1',
        'object' => 'chat.completion',
        'model' => 'review-1',
        'choices' => [['index' => 0, 'finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => json_encode(['verdict' => $verdict, 'reason' => 'Because'])]]],
        'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1, 'total_tokens' => 2],
    ];
}

beforeEach(function () {
    $this->withoutVite();
    Http::preventStrayRequests();
    safetyResolver();
    Artisan::call('mods:sync');
    $this->owner = User::factory()->create(['first_login' => false]);
    $this->party = Party::factory()->for($this->owner)->create(['provider_id' => 'fake', 'allow_requests' => true, 'explicit' => true])->refresh();
    $this->member = PartyMember::factory()->for($this->party)->for(User::factory())->create();
    $this->store = app(ModSettingsStore::class);
    foreach (['driver' => 'openai-compatible', 'endpoint' => 'https://llm.example.test/v1', 'model' => 'review-1', 'api_key' => 'secret'] as $code => $value) {
        $this->store->set($this->party, AiRequestReviewMod::CODE, $code, $value);
    }
    app(PartyMods::class)->enable($this->party, AiRequestReviewMod::CODE);
    $this->track = app(FakeMusicProvider::class)->getTrack('fake-track-1');
    $this->check = fn () => app(RequestRules::class)->check($this->party->refresh(), $this->member, $this->track);
});

it('sends openai-compatible requests without redirects and pinned to the resolved address', function () {
    safetyResolver(['llm.example.test' => ['93.184.216.99']]);
    $seen = [];
    Http::fake(function ($request, $options) use (&$seen) {
        $seen[] = $options;

        return Http::response(safetyChat('allow'));
    });

    ($this->check)();

    expect($seen)->toHaveCount(1)
        ->and($seen[0]['allow_redirects'])->toBeFalse()
        ->and($seen[0]['curl'][CURLOPT_RESOLVE])->toBe(['llm.example.test:443:93.184.216.99']);

    $after = null;
    Http::fake(function ($request, $options) use (&$after) {
        $after = $options;

        return Http::response('{}');
    });
    Http::get('https://other.example.test');
    expect($after)->not->toHaveKey('curl');
});

it('does not leave pinned options behind when the provider call fails', function () {
    safetyResolver(['llm.example.test' => ['93.184.216.99']]);
    Http::fake(['llm.example.test/*' => Http::response('boom', 500)]);

    expect(($this->check)()->outcome)->toBe(RequestOutcome::Allow);
    expect(app(PinnedClientOptions::class)->current())->toBe([]);

    $after = null;
    Http::fake(function ($request, $options) use (&$after) {
        $after = $options;

        return Http::response('{}');
    });
    Http::get('https://other.example.test');
    expect($after)->not->toHaveKey('curl')->and($after['allow_redirects'] ?? true)->not->toBeFalse();
});

it('does not follow a redirect to an internal address', function () {
    Http::fake([
        'llm.example.test/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest']),
        '169.254.169.254/*' => Http::response('metadata'),
    ]);

    expect(($this->check)()->outcome)->toBe(RequestOutcome::Allow);
    Http::assertSentCount(1);
});

it('rejects an internal endpoint when settings are saved, unless a site admin allows internal endpoints', function () {
    $payload = fn (string $endpoint) => ['ai-request-review' => ['settings' => ['endpoint' => $endpoint]]];
    $updater = app(ModSettingsUpdater::class);
    safetyResolver(['ollama' => ['172.18.0.5']]);

    foreach (['http://192.168.1.5:11434/v1', 'http://ollama:11434/v1'] as $endpoint) {
        try {
            $updater->updateParty($this->party, $payload($endpoint));
            $this->fail('Expected a validation error');
        } catch (ValidationException $e) {
            expect($e->errors()['mods.ai-request-review.settings.endpoint'][0])->toContain('internal address')->toContain('Allow internal endpoints');
        }
    }

    $this->store->setInstance(AiRequestReviewMod::CODE, 'allow_internal_endpoints', true);
    $updater->updateParty($this->party, $payload('http://ollama:11434/v1'));

    expect($this->store->value($this->party, AiRequestReviewMod::CODE, 'endpoint'))->toBe('http://ollama:11434/v1');
});

it('stops reusing cached verdicts once settings change', function () {
    Http::fake(['*' => Http::response(safetyChat('reject'))]);

    ($this->check)();
    ($this->check)();
    Http::assertSentCount(1);

    $this->store->set($this->party, AiRequestReviewMod::CODE, 'guidelines', 'No metal');
    ($this->check)();
    Http::assertSentCount(2);
});

it('records skipped outcomes for cached verdicts and an open breaker, and shows breaker state', function () {
    Http::fake(['*' => Http::response(safetyChat('allow'))]);
    ($this->check)();
    ($this->check)();

    $cached = AiReview::query()->latest('id')->first();
    expect($cached->outcome)->toBe(AiReviewOutcome::Skipped)->and($cached->reason)->toContain('cached');

    Cache::flush();
    foreach (range(1, AiReviewBreaker::FAILURE_THRESHOLD) as $i) {
        app(AiReviewBreaker::class)->recordFailure($this->party);
    }
    ($this->check)();
    expect(AiReview::query()->latest('id')->first())->outcome->toBe(AiReviewOutcome::Skipped)->reason->toContain('circuit breaker');

    $this->actingAs($this->owner)->get(route('parties.ai-reviews.index', $this->party->code))
        ->assertInertia(fn (Assert $page) => $page->where('breaker.open', true)->where('breaker.threshold', AiReviewBreaker::FAILURE_THRESHOLD));
});

it('shows the last failure in the admin mods screen', function () {
    AiReview::factory()->for($this->party)->create(['outcome' => AiReviewOutcome::Error, 'error_message' => 'Decision model responded with HTTP 500']);
    $admin = User::factory()->create(['first_login' => false]);
    $admin->roles()->attach(Role::query()->firstWhere('code', 'admin') ?? Role::query()->forceCreate(['code' => 'admin', 'name' => 'Admin']));

    $this->actingAs($admin)->get(route('admin.mods.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('mods', fn ($mods) => collect($mods)->firstWhere('code', 'ai-request-review')['review_stats']['last_failure']['message'] === 'Decision model responded with HTTP 500'));
});
