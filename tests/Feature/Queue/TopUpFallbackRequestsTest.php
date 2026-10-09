<?php

use App\Domain\Music\Data\AlbumData;
use App\Domain\Music\Data\TrackData;
use App\Domain\Music\Exceptions\ProviderTemporaryFailure;
use App\Domain\Music\Testing\FakeMusicProvider;
use App\Domain\Queue\Actions\TopUpFallbackRequests;
use App\Domain\Queue\RequestStatus;
use App\Models\Party;
use App\Models\Play;
use App\Models\TrackRequest;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => CarbonImmutable::setTestNow('2026-01-01 12:00:00'));
afterEach(fn () => CarbonImmutable::setTestNow());

function queuedTrackIds(Party $party): array
{
    return TrackRequest::query()->where('party_id', $party->id)->where('status', RequestStatus::Queued)->pluck('provider_track_id')->sort()->values()->all();
}

it('tops the queue up to the configured minimum with requester-less fallback requests', function () {
    $party = livePlaybackParty(array_map(playbackTrack(...), range(1, 12)));

    expect(app(TopUpFallbackRequests::class)($party))->toBe(5)
        ->and(TrackRequest::query()->whereNull('party_member_id')->where('status', RequestStatus::Queued)->count())->toBe(5)
        ->and(app(TopUpFallbackRequests::class)($party))->toBe(0);
});

it('honours a configured minimum and only fills the shortfall', function () {
    config(['musicparty.fallback_minimum_queue' => 3]);
    $party = livePlaybackParty(array_map(playbackTrack(...), range(1, 10)));
    TrackRequest::factory()->for($party)->create(['provider_track_id' => 'p1']);

    expect(app(TopUpFallbackRequests::class)($party))->toBe(2)
        ->and(queuedTrackIds($party))->toHaveCount(3);
});

it('skips tracks already queued, up next, playing or pending', function () {
    $party = livePlaybackParty(array_map(playbackTrack(...), range(1, 8)));
    foreach ([['p1', RequestStatus::Queued], ['p2', RequestStatus::UpNext], ['p3', RequestStatus::Playing], ['p4', RequestStatus::Pending]] as [$id, $status]) {
        TrackRequest::factory()->for($party)->create(['provider_track_id' => $id, 'status' => $status]);
    }

    app(TopUpFallbackRequests::class)($party);

    expect(queuedTrackIds($party))->toHaveCount(5)->not->toContain('p2', 'p3', 'p4')
        ->and(TrackRequest::query()->where('provider_track_id', 'p1')->count())->toBe(1);
});

it('skips tracks played within the no-repeat interval but not older plays', function () {
    $party = livePlaybackParty(array_map(playbackTrack(...), range(1, 6)), ['no_repeat_interval' => 3600]);
    Play::factory()->for($party)->create(['provider_track_id' => 'p1', 'played_at' => now()->subMinutes(10)]);
    Play::factory()->for($party)->create(['provider_track_id' => 'p2', 'played_at' => now()->subHours(2)]);

    app(TopUpFallbackRequests::class)($party);

    expect(queuedTrackIds($party))->toHaveCount(5)->not->toContain('p1')->toContain('p2');
});

it('applies explicit, length and market rules', function () {
    $party = livePlaybackParty([
        playbackTrack(1, explicit: true),
        playbackTrack(2, durationMs: 30000),
        playbackTrack(3, durationMs: 900000),
        new TrackData('fake', 'p4', 'Locked', [], new AlbumData('al', 'Album'), 180000, playableInMarket: false),
        playbackTrack(5),
    ], ['explicit' => false, 'min_song_length' => 60, 'max_song_length' => 600]);

    expect(app(TopUpFallbackRequests::class)($party))->toBe(1)
        ->and(queuedTrackIds($party))->toBe(['p5']);
});

it('creates as many as possible when the playlist is short', function () {
    $party = livePlaybackParty([playbackTrack(1), playbackTrack(2)]);

    expect(app(TopUpFallbackRequests::class)($party))->toBe(2);
});

it('does nothing without a playlist, when not live, or when the provider fails', function () {
    $party = livePlaybackParty([playbackTrack(1)], ['fallback_playlist_id' => null]);
    expect(app(TopUpFallbackRequests::class)($party))->toBe(0);

    $party = livePlaybackParty([playbackTrack(1)], ['state' => 'paused']);
    expect(app(TopUpFallbackRequests::class)($party))->toBe(0);

    $party = livePlaybackParty([playbackTrack(1)]);
    app(FakeMusicProvider::class)->failNextWith(new ProviderTemporaryFailure('down', 5));
    expect(app(TopUpFallbackRequests::class)($party))->toBe(0)
        ->and(TrackRequest::query()->count())->toBe(0);
});

it('shuffles the playlist rather than always taking the head', function () {
    $party = livePlaybackParty(array_map(playbackTrack(...), range(1, 60)));

    app(TopUpFallbackRequests::class)($party);

    expect(queuedTrackIds($party))->not->toBe(['p1', 'p2', 'p3', 'p4', 'p5']);
});
