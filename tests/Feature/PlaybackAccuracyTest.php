<?php

use App\Console\Commands\PartyFallback;
use App\Events\Party\UpdatedEvent as PartyUpdatedEvent;
use App\Jobs\PartyUpdate;
use App\Models\Party;
use App\Services\Dashboard\PartyPlayerControl;
use App\Services\Music\Data\AlbumRef;
use App\Services\Music\Data\ArtistRef;
use App\Services\Music\Data\PlaybackSource;
use App\Services\Music\Data\PlaybackState;
use App\Services\Music\Data\Track;
use App\Services\Music\Fake\FakeMusicProvider;
use App\Services\Playback\PartyDashboardCache;
use App\Services\PlaybackStatus;
use App\Services\PlaybackSync;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeSpotify;

function accuracyState(int $progressMs, CarbonImmutable $sampledAt, bool $isPlaying = true): PlaybackState
{
    return new PlaybackState($isPlaying, 'A', null, 200000, $progressMs, $sampledAt, false, 'off', 'dev-1', 'Device', PlaybackSource::Webhook);
}

beforeEach(function () {
    FakeSpotify::install();
    $this->party = Party::factory()->create(['provider_id' => 'fake', 'active' => true, 'poll' => true, 'backup_playlist_id' => 'BACKUP']);
    Cache::flush();
    app(FakeMusicProvider::class)->withTrack(new Track('fake', 'A', 'Name A', 200000, false, true, new AlbumRef('album-A', 'Album', null), [new ArtistRef('artist1', 'Artist')]));
    $this->t0 = CarbonImmutable::now()->subMinute();
    $this->sync = app(PlaybackSync::class);
    $this->sync->apply($this->party, accuracyState(10000, $this->t0));
});

it('broadcasts seeks and restarts of the same track but not normal progress', function (int $progressMs, int $expected) {
    Event::fake([PartyUpdatedEvent::class]);

    $this->sync->apply($this->party->fresh(), accuracyState($progressMs, $this->t0->addSeconds(10)));

    Event::assertDispatchedTimes(PartyUpdatedEvent::class, $expected);
})->with([
    'normal progress' => [20500, 0],
    'seek forward 30s' => [50000, 1],
    'seek back' => [1000, 1],
    'restart' => [0, 1],
]);

it('does not extrapolate while paused', function () {
    $this->sync->apply($this->party->fresh(), accuracyState(10000, $this->t0->addSeconds(1), false));
    Event::fake([PartyUpdatedEvent::class]);

    $this->sync->apply($this->party->fresh(), accuracyState(10500, $this->t0->addSeconds(30), false));

    Event::assertNotDispatched(PartyUpdatedEvent::class);
});

it('rejects a stale frame older than a progress-only frame that did not advance the persisted stamp', function () {
    $this->sync->apply($this->party->fresh(), accuracyState(20000, $this->t0->addSeconds(10)));
    $persisted = $this->party->fresh()->playback_sampled_at;
    $this->sync->apply($this->party->fresh(), accuracyState(25000, $this->t0->addSeconds(15)));
    expect($this->party->fresh()->playback_sampled_at->equalTo($persisted))->toBeTrue();

    $this->sync->apply($this->party->fresh(), accuracyState(1000, $this->t0->addSeconds(12), false));

    expect(app(PlaybackStatus::class)->recall($this->party)->progressMs)->toBe(25000);
});

it('party:fallback skips a party whose last-synced marker is fresh and dispatches once it is old', function () {
    Queue::fake();
    $this->party->forceFill(['last_updated_at' => now()->subMinutes(5)])->saveQuietly();
    app(PlaybackStatus::class)->markSynced($this->party);

    $this->artisan(PartyFallback::class)->assertSuccessful();
    Queue::assertNothingPushed();

    $this->travel(100)->seconds();
    $this->artisan(PartyFallback::class)->assertSuccessful();
    Queue::assertPushed(PartyUpdate::class, 1);
});

it('keeps the last-synced marker fresh on progress-only frames', function () {
    $this->travel(60)->seconds();
    $this->sync->apply($this->party->fresh(), accuracyState(70000, $this->t0->addSeconds(60)));

    expect(app(PlaybackStatus::class)->lastSyncedAt($this->party)->greaterThan(now()->subSeconds(5)))->toBeTrue();
});

it('does not trust a remembered idle state older than 30 seconds', function (int $ageSeconds, int $calls) {
    $fake = app(FakeMusicProvider::class);
    app(PlaybackStatus::class)->remember($this->party, new PlaybackState(false, null, null, null, 0, CarbonImmutable::now()->subSeconds($ageSeconds), false, 'off', null, null, PlaybackSource::Polling));
    Cache::forget(PartyDashboardCache::playbackKey($this->party));
    $before = count($fake->callsTo('currentPlayback'));

    app(PartyPlayerControl::class)->playback($this->party);

    expect(count($fake->callsTo('currentPlayback')) - $before)->toBe($calls);
})->with([
    'fresh' => [10, 0],
    'old' => [45, 1],
]);
