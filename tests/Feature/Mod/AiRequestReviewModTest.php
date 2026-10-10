<?php

use App\Domain\Membership\Models\PartyMember;
use App\Domain\Mod\AiReview\AiRequestReviewer;
use App\Domain\Mod\AiReview\AiRequestReviewMod;
use App\Domain\Mod\Jobs\ReviewRequestWithAi;
use App\Domain\Mod\ModRegistry;
use App\Domain\Mod\ModSettings;
use App\Domain\Music\Testing\FakeMusicProvider;
use App\Domain\Party\Models\Party;
use App\Domain\Party\Models\PartyLogEntry;
use App\Domain\Playback\Jobs\StartPlayback;
use App\Domain\Queue\Actions\RequestTrack;
use App\Domain\Queue\Broadcast\PendingRequestResolvedEvent;
use App\Domain\Queue\Exceptions\RequestRefusedException;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\RequestStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Classification;
use Laravel\Ai\Prompts\ClassificationPrompt;
use Laravel\Ai\Responses\Data\ScoreAnswer;
use Tests\Fixtures\Mods\ModFixtures;
use Tests\Fixtures\Mods\RejectingRuleMod;

uses(RefreshDatabase::class);

function aiScore(float $level): array
{
    return ['suitability' => new ScoreAnswer($level, [], ['a', 'b', 'c'], 0.9)];
}

function enableAiReview(Party $party, array $settings = []): void
{
    $mod = app(ModRegistry::class)->find(AiRequestReviewMod::ID);
    $defaults = app(ModSettings::class)->defaults($mod);

    ModFixtures::enable($party, $mod, app(ModSettings::class)->encrypt($mod, [...$defaults, 'openai_api_key' => 'sk-test', 'jev_api_key' => 'jev-test', ...$settings]));
}

beforeEach(function () {
    Bus::fake([StartPlayback::class]);
    app()->instance(FakeMusicProvider::class, FakeMusicProvider::withDefaultCatalogue());
    $this->party = Party::factory()->live()->create(['hold_requests' => false]);
    $this->member = PartyMember::factory()->for($this->party)->create();
    $this->pending = fn (): TrackRequest => TrackRequest::factory()->for($this->party)->for($this->member, 'requester')->status(RequestStatus::Pending)->create();
    $this->review = fn (TrackRequest $request) => app(AiRequestReviewer::class)->review($this->party, $request->id);
});

it('registers the Mod with its settings', function () {
    $mod = app(ModRegistry::class)->find('ai-request-review');

    expect($mod)->not->toBeNull()
        ->and(array_map(fn ($setting) => $setting->key, $mod->settings()))->toContain('driver', 'rubric', 'hold_threshold', 'reject_threshold', 'failure_behaviour');
});

it('holds a request as Pending and queues the review on mods-ai', function () {
    Queue::fake();
    enableAiReview($this->party);

    $outcome = app(RequestTrack::class)($this->party, $this->member, 'track-1');

    expect($outcome->request->status)->toBe(RequestStatus::Pending);
    Queue::assertPushedOn('mods-ai', ReviewRequestWithAi::class, fn (ReviewRequestWithAi $job) => $job->requestId === $outcome->request->id && $job->partyId === $this->party->id);
});

it('does not queue a review when another Mod rejects the request', function () {
    Queue::fake();
    enableAiReview($this->party);
    ModFixtures::enable($this->party, new RejectingRuleMod);

    expect(fn () => app(RequestTrack::class)($this->party, $this->member, 'track-1'))->toThrow(RequestRefusedException::class);

    Queue::assertNotPushed(ReviewRequestWithAi::class);
});

it('queues the review for each request even when the same track is requested again later', function () {
    Queue::fake();
    enableAiReview($this->party);

    app(RequestTrack::class)($this->party, $this->member, 'track-1');
    app(RequestTrack::class)($this->party, $this->member, 'track-1');

    Queue::assertPushed(ReviewRequestWithAi::class, 1);
});

it('accepts an acceptable track into the queue', function () {
    Classification::fake([aiScore(0.2)]);
    enableAiReview($this->party);
    Event::fake([PendingRequestResolvedEvent::class]);
    $request = ($this->pending)();

    ($this->review)($request);

    expect($request->fresh()->status)->toBe(RequestStatus::Queued);
    Event::assertDispatched(PendingRequestResolvedEvent::class);
    Bus::assertDispatched(StartPlayback::class);
    expect(PartyLogEntry::query()->where('action', 'mod.request_approved')->sole()->system_actor)->toBe('mod:ai-request-review');
});

it('rejects a track above the reject threshold, with the reason and a log entry', function () {
    Classification::fake([aiScore(1.8)]);
    enableAiReview($this->party);
    $request = ($this->pending)();

    ($this->review)($request);

    $request->refresh();
    expect($request->status)->toBe(RequestStatus::Rejected)
        ->and($request->rejection_reason)->toBe('Rated 90/100 for unsuitability by AI review.');
    expect(PartyLogEntry::query()->where('action', 'mod.request_rejected')->sole()->details)->toMatchArray(['request_id' => $request->id]);
});

it('keeps an uncertain track Pending for a human and logs the hold', function () {
    Classification::fake([aiScore(1.0)]);
    enableAiReview($this->party);
    $request = ($this->pending)();

    ($this->review)($request);

    expect($request->fresh()->status)->toBe(RequestStatus::Pending);
    expect(PartyLogEntry::query()->where('action', 'mod.request_held')->sole()->details)->toMatchArray(['request_id' => $request->id, 'reason' => 'Rated 50/100 for unsuitability by AI review.']);
});

it('treats a score exactly on a threshold as that outcome', function (float $level, RequestStatus $expected) {
    Classification::fake([aiScore($level)]);
    enableAiReview($this->party, ['hold_threshold' => 50, 'reject_threshold' => 100]);
    $request = ($this->pending)();

    ($this->review)($request);

    expect($request->fresh()->status)->toBe($expected);
})->with([
    'hold boundary' => [1.0, RequestStatus::Pending],
    'just under hold' => [0.98, RequestStatus::Queued],
    'reject boundary' => [2.0, RequestStatus::Rejected],
]);

it('applies the failure behaviour when the classifier times out', function (string $behaviour, RequestStatus $expected) {
    Classification::fake(fn () => throw new RuntimeException('cURL error 28: Operation timed out'));
    enableAiReview($this->party, ['failure_behaviour' => $behaviour]);
    $request = ($this->pending)();

    ($this->review)($request);

    expect($request->fresh()->status)->toBe($expected);
    expect(PartyLogEntry::query()->where('action', 'mod.rule_failed')->sole()->details)->toMatchArray(['request_id' => $request->id, 'error' => 'cURL error 28: Operation timed out']);
})->with([
    'accept' => ['accept', RequestStatus::Queued],
    'reject' => ['reject', RequestStatus::Rejected],
    'hold' => ['hold', RequestStatus::Pending],
]);

it('applies the fallback verdict when the job is killed at its timeout', function () {
    enableAiReview($this->party, ['failure_behaviour' => 'accept']);
    $request = ($this->pending)();

    new ReviewRequestWithAi($this->party->id, $request->id)->failed(new RuntimeException('timed out'));

    expect($request->fresh()->status)->toBe(RequestStatus::Queued);
    expect(PartyLogEntry::query()->where('action', 'mod.rule_failed')->sole()->details)->toMatchArray(['error' => 'timed out']);
});

it('runs the queued job end to end', function () {
    Classification::fake([aiScore(0.0)]);
    enableAiReview($this->party);
    $request = ($this->pending)();

    new ReviewRequestWithAi($this->party->id, $request->id)->handle(app(AiRequestReviewer::class));

    expect($request->fresh()->status)->toBe(RequestStatus::Queued);
});

it('selects the provider for the chosen driver', function (string $driver, string $provider) {
    Classification::fake([aiScore(0.0)]);
    enableAiReview($this->party, ['driver' => $driver]);

    ($this->review)(($this->pending)());

    Classification::assertClassified(fn (ClassificationPrompt $prompt) => $prompt->provider->name() !== '' && str_contains($prompt->provider::class, $provider));
})->with([
    'openai' => ['openai', 'OpenAi'],
    'jev' => ['jev', 'TypeSafe'],
]);

it('sends only track metadata and the rubric, with no member identity', function () {
    Classification::fake([aiScore(0.0)]);
    enableAiReview($this->party, ['rubric' => 'No metal.']);
    $request = ($this->pending)();

    ($this->review)($request);

    Classification::assertClassified(function (ClassificationPrompt $prompt) use ($request) {
        $encoded = json_encode([$prompt->state, array_map(fn ($question) => $question->toArray(), $prompt->questions)]);

        return array_keys($prompt->state) === ['title', 'artists', 'album', 'explicit']
            && $prompt->state['title'] === $request->title
            && str_contains($encoded, 'No metal.')
            && ! str_contains($encoded, $this->member->id.'@')
            && ! str_contains($encoded, 'email');
    });
});

it('holds without a review when credentials are missing, per the failure behaviour', function (string $behaviour, RequestStatus $expected) {
    Queue::fake();
    $mod = app(ModRegistry::class)->find(AiRequestReviewMod::ID);
    ModFixtures::enable($this->party, $mod, ['failure_behaviour' => $behaviour]);

    if ($expected === RequestStatus::Rejected) {
        expect(fn () => app(RequestTrack::class)($this->party, $this->member, 'track-1'))->toThrow(RequestRefusedException::class);
    } else {
        expect(app(RequestTrack::class)($this->party, $this->member, 'track-1')->request->status)->toBe($expected);
    }

    Queue::assertNotPushed(ReviewRequestWithAi::class);
    expect(PartyLogEntry::query()->where('action', 'mod.rule_failed')->count())->toBe(1);
})->with([
    'accept' => ['accept', RequestStatus::Queued],
    'hold' => ['hold', RequestStatus::Pending],
    'reject' => ['reject', RequestStatus::Rejected],
]);

it('leaves a request alone once it is no longer Pending', function () {
    Classification::fake([aiScore(2.0)]);
    enableAiReview($this->party);
    $request = ($this->pending)();
    $request->forceFill(['status' => RequestStatus::Removed])->save();

    ($this->review)($request);

    expect($request->fresh()->status)->toBe(RequestStatus::Removed);
    Classification::assertNothingClassified();
});

it('does nothing when the Mod was disabled before the job ran', function () {
    Classification::fake([aiScore(2.0)]);
    $request = ($this->pending)();

    ($this->review)($request);

    expect($request->fresh()->status)->toBe(RequestStatus::Pending);
    Classification::assertNothingClassified();
});
