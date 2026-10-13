<?php

use App\Jobs\RefreshFallbackPlaylist;
use App\Models\Party;
use App\Models\UpcomingSong;
use App\Services\FallbackPlaylist;
use App\Services\Music\Data\Playlist;
use App\Services\Music\Fake\FakeMusicProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeFallbackPlaylist;

function fallbackParty(?string $playlistId = 'BACKUP'): Party
{
    Queue::fake();
    $party = Party::factory()->create(['backup_playlist_id' => $playlistId]);
    Cache::flush();
    app()->forgetInstance(FakeMusicProvider::class);

    return $party;
}

function trackIds(array $tracks): array
{
    return array_map(fn ($track) => $track->id, $tracks);
}

it('caches tracks and refetches when forced', function () {
    $party = fallbackParty();
    FakeFallbackPlaylist::seed(['A', 'B']);
    $service = app(FallbackPlaylist::class);

    expect(trackIds($service->tracks($party)))->toBe(['A', 'B']);
    $service->tracks($party);
    expect(app(FakeMusicProvider::class)->callsTo('getPlaylistTracks'))->toHaveCount(1)
        ->and(Cache::has("party.{$party->id}.backupplaylist"))->toBeTrue();

    FakeFallbackPlaylist::seed(['C']);
    expect(trackIds($service->tracks($party, true)))->toBe(['C'])
        ->and(trackIds($service->tracks($party)))->toBe(['C'])
        ->and(app(FakeMusicProvider::class)->callsTo('getPlaylistTracks'))->toHaveCount(2);
});

it('returns an empty list without a backup playlist id', function () {
    $party = fallbackParty(null);

    expect(app(FallbackPlaylist::class)->tracks($party))->toBe([])
        ->and(app(FakeMusicProvider::class)->callsTo('getPlaylistTracks'))->toBe([]);
});

it('filters unplayable tracks', function () {
    $party = fallbackParty();
    app(FakeMusicProvider::class)->withPlaylist(new Playlist('BACKUP', 'Fallback', 'Owner', 2), [
        FakeFallbackPlaylist::track('BAD', false),
        FakeFallbackPlaylist::track('GOOD'),
    ]);

    expect(trackIds(app(FallbackPlaylist::class)->tracks($party)))->toBe(['GOOD']);
});

it('backfills up to the minimum upcoming and creates songs', function () {
    $party = fallbackParty();
    FakeFallbackPlaylist::seed(['A', 'B', 'C', 'D', 'E', 'F', 'G']);

    app(FallbackPlaylist::class)->backfill($party);

    $upcoming = UpcomingSong::query()->where('party_id', $party->id)->whereNull('queued_at')->with('song')->get();
    expect($upcoming)->toHaveCount(Party::MINIMUM_UPCOMING)
        ->and($upcoming->pluck('song.spotify_id')->unique()->all())->each->toBeIn(['A', 'B', 'C', 'D', 'E', 'F', 'G']);
});

it('dispatches a refresh job without fetching when the backup playlist id changes', function () {
    Queue::fake();
    $party = Party::factory()->create(['backup_playlist_id' => 'ONE']);
    Queue::assertPushed(RefreshFallbackPlaylist::class, 1);

    $party->backup_playlist_id = 'TWO';
    $party->save();

    Queue::assertPushed(RefreshFallbackPlaylist::class, 2);
    expect(app(FakeMusicProvider::class)->callsTo('getPlaylistTracks'))->toBe([]);
});

it('does not dispatch a refresh job when the backup playlist id is unchanged', function () {
    Queue::fake();
    $party = Party::factory()->create(['backup_playlist_id' => 'ONE']);

    $party->name = 'Renamed';
    $party->save();

    Queue::assertPushed(RefreshFallbackPlaylist::class, 1);
});

it('populates the cache when the refresh job runs', function () {
    $party = fallbackParty();
    FakeFallbackPlaylist::seed(['A', 'B']);

    (new RefreshFallbackPlaylist($party))->handle(app(FallbackPlaylist::class));

    expect(trackIds(Cache::get("party.{$party->id}.backupplaylist")))->toBe(['A', 'B']);
});
