<?php

use App\Domain\Party\Actions\GoLiveParty;
use App\Domain\Playback\FeedMode;
use App\Domain\Playback\PlaybackCoordinator;
use App\Domain\Playback\PlaybackStatus;
use App\Domain\Queue\Actions\RequestTrack;
use App\Domain\Queue\Actions\SelectUpNext;
use App\Domain\Queue\RequestStatus;
use App\Jobs\BroadcastPartyQueue;
use App\Jobs\StartPlayback;
use App\Jobs\TickPlayback;
use App\Models\Party;
use App\Models\PartyLogEntry;
use App\Models\PartyMember;
use App\Models\Play;
use App\Models\RequestVote;
use App\Models\TrackRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-01-01 12:00:00');
    Bus::fake([BroadcastPartyQueue::class]);
});

afterEach(fn () => CarbonImmutable::setTestNow());

function requestStatuses(Party $party): array
{
    return TrackRequest::query()->where('party_id', $party->id)->orderBy('id')->get()
        ->mapWithKeys(fn (TrackRequest $request): array => [$request->provider_track_id => $request->status->value])->all();
}

it('starts the first request immediately when a party goes live with an idle player', function (FeedMode $mode) {
    $party = livePlaybackParty();
    $player = useFakePlayer($party, $mode);
    $first = TrackRequest::factory()->for($party)->create(['provider_track_id' => 'r1']);
    TrackRequest::factory()->for($party)->create(['provider_track_id' => 'r2']);

    app(PlaybackCoordinator::class)->startIfIdle($party);

    expect($player->state()->status)->toBe(PlaybackStatus::Playing)
        ->and($player->state()->currentTrack->providerTrackId)->toBe('r1')
        ->and($first->fresh()->status)->toBe(RequestStatus::Playing)
        ->and($first->fresh()->started_at->equalTo(now()))->toBeTrue();
})->with([FeedMode::Ahead, FeedMode::JustInTime]);

it('keeps exactly one Up Next enqueued ahead of the current track and reselects on every change', function () {
    $party = livePlaybackParty();
    $player = useFakePlayer($party, FeedMode::Ahead);
    foreach (['r1' => 3, 'r2' => 2, 'r3' => 1] as $id => $created) {
        TrackRequest::factory()->for($party)->create(['provider_track_id' => $id, 'created_at' => now()->subMinutes($created)]);
    }

    app(PlaybackCoordinator::class)->startIfIdle($party);

    expect(requestStatuses($party))->toBe(['r1' => 'playing', 'r2' => 'up_next', 'r3' => 'queued'])
        ->and(enqueuedTrackIds($player))->toBe(['r1', 'r2'])
        ->and($player->queued())->toHaveCount(1);

    $player->advance();

    expect(requestStatuses($party))->toBe(['r1' => 'played', 'r2' => 'playing', 'r3' => 'up_next'])
        ->and(enqueuedTrackIds($player))->toBe(['r1', 'r2', 'r3'])
        ->and(Play::query()->pluck('provider_track_id')->all())->toBe(['r1']);
});

it('selects and sends the next track about 15 seconds before the end in just-in-time mode, exactly once', function () {
    $party = livePlaybackParty();
    $player = useFakePlayer($party, FeedMode::JustInTime);
    TrackRequest::factory()->for($party)->create(['provider_track_id' => 'r1', 'duration_ms' => 100000]);
    TrackRequest::factory()->for($party)->create(['provider_track_id' => 'r2']);
    $coordinator = app(PlaybackCoordinator::class);
    $coordinator->startIfIdle($party);

    expect(requestStatuses($party))->toBe(['r1' => 'playing', 'r2' => 'queued']);

    CarbonImmutable::setTestNow(now()->addSeconds(84));
    $coordinator->tick($party);
    expect(requestStatuses($party)['r2'])->toBe('queued')->and(enqueuedTrackIds($player))->toBe(['r1']);

    CarbonImmutable::setTestNow(now()->addSeconds(1));
    $coordinator->tick($party);
    $coordinator->tick($party);
    $coordinator->tick($party);

    expect(requestStatuses($party)['r2'])->toBe('up_next')->and(enqueuedTrackIds($player))->toBe(['r1', 'r2']);

    $player->advance();

    expect(requestStatuses($party))->toBe(['r1' => 'played', 'r2' => 'playing']);
});

it('counts votes that arrive before the just-in-time lock and ignores them after', function () {
    $party = livePlaybackParty();
    $player = useFakePlayer($party, FeedMode::JustInTime);
    TrackRequest::factory()->for($party)->create(['provider_track_id' => 'r1', 'duration_ms' => 100000, 'created_at' => now()->subMinutes(2)]);
    $early = TrackRequest::factory()->for($party)->create(['provider_track_id' => 'r2', 'created_at' => now()->subMinute()]);
    $late = TrackRequest::factory()->for($party)->create(['provider_track_id' => 'r3']);
    $coordinator = app(PlaybackCoordinator::class);
    $coordinator->startIfIdle($party);

    RequestVote::factory()->create(['track_request_id' => $late->id]);
    CarbonImmutable::setTestNow(now()->addSeconds(90));
    $coordinator->tick($party);

    expect($late->fresh()->status)->toBe(RequestStatus::UpNext);

    RequestVote::factory()->count(3)->create(['track_request_id' => $early->id]);
    $coordinator->tick($party);

    expect($early->fresh()->status)->toBe(RequestStatus::Queued)->and(enqueuedTrackIds($player))->toBe(['r1', 'r3']);
});

it('sends only a single enqueue for duplicate triggers', function (FeedMode $mode) {
    $party = livePlaybackParty();
    $player = useFakePlayer($party, $mode);
    TrackRequest::factory()->for($party)->count(3)->create();
    $coordinator = app(PlaybackCoordinator::class);

    $coordinator->startIfIdle($party);
    $coordinator->startIfIdle($party);
    $coordinator->tick($party);
    $coordinator->tick($party);

    expect(TrackRequest::query()->where('status', RequestStatus::UpNext)->count())->toBe($mode === FeedMode::Ahead ? 1 : 0)
        ->and(count(enqueuedTrackIds($player)))->toBe($mode === FeedMode::Ahead ? 2 : 1);
})->with([FeedMode::Ahead, FeedMode::JustInTime]);

it('locks a single Up Next when racing selections arrive with stale party instances', function () {
    $party = livePlaybackParty();
    useFakePlayer($party, FeedMode::Ahead)->enqueue('fake', 'already-playing');
    TrackRequest::factory()->for($party)->count(2)->create();
    $stale = Party::query()->find($party->id);

    $results = [app(SelectUpNext::class)($stale), app(SelectUpNext::class)($party), app(SelectUpNext::class)($stale)];

    expect(array_filter($results))->toHaveCount(1)
        ->and(TrackRequest::query()->where('status', RequestStatus::UpNext)->count())->toBe(1);
});

it('retries sending an Up Next the player refused while disconnected', function () {
    $party = livePlaybackParty();
    $player = useFakePlayer($party, FeedMode::Ahead);
    TrackRequest::factory()->for($party)->create(['provider_track_id' => 'r1']);
    $player->disconnect();
    $coordinator = app(PlaybackCoordinator::class);

    $coordinator->startIfIdle($party);

    expect(requestStatuses($party))->toBe(['r1' => 'up_next'])->and(enqueuedTrackIds($player))->toBe([]);

    $player->reconnect();
    $coordinator->tick($party);

    expect(requestStatuses($party))->toBe(['r1' => 'playing']);
});

it('creates a fallback request when selection is due with an empty queue', function () {
    $party = livePlaybackParty(array_map(playbackTrack(...), range(1, 8)));
    $player = useFakePlayer($party);

    app(PlaybackCoordinator::class)->startIfIdle($party);

    expect($player->state()->status)->toBe(PlaybackStatus::Playing)
        ->and(TrackRequest::query()->where('status', RequestStatus::Playing)->first()->party_member_id)->toBeNull()
        ->and(TrackRequest::query()->where('status', RequestStatus::UpNext)->count())->toBe(1)
        ->and(TrackRequest::query()->where('status', RequestStatus::Queued)->count())->toBe(4);
});

it('records a Play with track info, requester, time, mode and score when a request finishes', function () {
    $party = livePlaybackParty();
    $player = useFakePlayer($party);
    $request = TrackRequest::factory()->for($party)->create(['provider_track_id' => 'r1', 'title' => 'Song']);
    RequestVote::factory()->count(2)->create(['track_request_id' => $request->id]);
    app(PlaybackCoordinator::class)->startIfIdle($party);

    CarbonImmutable::setTestNow(now()->addMinutes(3));
    $player->advance();

    $play = Play::query()->sole();
    expect($request->fresh()->status)->toBe(RequestStatus::Played)
        ->and($play->party_id)->toBe($party->id)
        ->and($play->party_member_id)->toBe($request->party_member_id)
        ->and($play->title)->toBe('Song')
        ->and($play->provider_track_id)->toBe('r1')
        ->and($play->selection_mode)->toBe('deterministic')
        ->and($play->selection_score)->toBe(2)
        ->and($play->played_at->equalTo(now()))->toBeTrue()
        ->and($player->state()->status)->toBe(PlaybackStatus::Stopped);
});

it('records a Play without a requester for fallback requests', function () {
    $party = livePlaybackParty([playbackTrack(1)]);
    $player = useFakePlayer($party);
    app(PlaybackCoordinator::class)->startIfIdle($party);

    $player->advance();

    expect(Play::query()->sole()->party_member_id)->toBeNull();
});

it('stops the player and locks nothing when nothing is eligible and the provider autoplays', function (FeedMode $mode) {
    $party = livePlaybackParty([playbackTrack(1)], ['no_repeat_interval' => 3600]);
    $player = useFakePlayer($party, $mode);
    $coordinator = app(PlaybackCoordinator::class);
    $coordinator->startIfIdle($party);
    expect($player->state()->currentTrack->providerTrackId)->toBe('p1');

    $coordinator->tick($party);
    $player->autoplay('fake', 'provider-autoplay');

    expect($player->state()->status)->toBe(PlaybackStatus::Paused)
        ->and(array_last($player->commands())->type)->toBe('pause')
        ->and(enqueuedTrackIds($player))->toBe(['p1'])
        ->and(TrackRequest::query()->whereIn('status', [RequestStatus::UpNext, RequestStatus::Playing])->count())->toBe(0)
        ->and(requestStatuses($party))->toBe(['p1' => 'played'])
        ->and(PartyLogEntry::query()->where('action', 'player.stopped')->count())->toBe(1);
})->with([FeedMode::Ahead, FeedMode::JustInTime]);

it('does not select anything for a party that is not live', function () {
    $party = livePlaybackParty([], ['state' => 'paused']);
    $player = useFakePlayer($party);
    TrackRequest::factory()->for($party)->create();

    app(PlaybackCoordinator::class)->startIfIdle($party);
    app(PlaybackCoordinator::class)->tick($party);

    expect($player->commands())->toBe([])->and(TrackRequest::query()->where('status', RequestStatus::Queued)->count())->toBe(1);
});

it('broadcasts the queue after each transition', function () {
    $party = livePlaybackParty();
    $player = useFakePlayer($party);
    TrackRequest::factory()->for($party)->count(2)->create();

    app(PlaybackCoordinator::class)->startIfIdle($party);
    $player->advance();

    Bus::assertDispatched(BroadcastPartyQueue::class, fn (BroadcastPartyQueue $job): bool => $job->partyCode === $party->code);
});

it('triggers playback when a party goes live and when a first request arrives', function () {
    Bus::fake([StartPlayback::class, BroadcastPartyQueue::class]);
    $party = livePlaybackParty(array_map(playbackTrack(...), range(1, 20)), ['state' => 'paused']);
    $host = User::factory()->create();

    $party = app(GoLiveParty::class)($host, $party);
    Bus::assertDispatched(StartPlayback::class, fn (StartPlayback $job): bool => $job->partyCode === $party->code);

    $member = PartyMember::factory()->for($party)->create();
    app(RequestTrack::class)($party, $member, 'p1');
    Bus::assertDispatchedTimes(StartPlayback::class, 2);
});

it('ticks only live parties from the scheduled job and survives one failing party', function () {
    $live = livePlaybackParty();
    $player = useFakePlayer($live, FeedMode::Ahead);
    TrackRequest::factory()->for($live)->create(['provider_track_id' => 'r1']);
    $paused = Party::factory()->create();
    TrackRequest::factory()->for($paused)->create();

    (new TickPlayback)->handle(app(PlaybackCoordinator::class));

    expect($player->state()->currentTrack->providerTrackId)->toBe('r1')
        ->and(TrackRequest::query()->where('party_id', $paused->id)->first()->status)->toBe(RequestStatus::Queued);
});

it('schedules the playback tick', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event): bool => str_contains((string) $event->description, TickPlayback::class) || str_contains($event->command ?? '', 'TickPlayback'));

    expect($events)->not->toBeEmpty();
});
