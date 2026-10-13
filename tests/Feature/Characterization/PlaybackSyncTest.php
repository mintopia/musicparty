<?php

use App\Events\Party\UpdatedEvent as PartyUpdatedEvent;
use App\Models\Party;
use App\Models\PlayedSong;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Services\Music\Data\AlbumRef;
use App\Services\Music\Data\ArtistRef;
use App\Services\Music\Data\PlaybackSource;
use App\Services\Music\Data\PlaybackState;
use App\Services\Music\Data\Track;
use App\Services\Music\Fake\FakeMusicProvider;
use App\Services\PlaybackSync;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\Support\FakeSpotify;

function syncParty(array $attributes = []): array
{
    FakeSpotify::install();
    $party = Party::factory()->create(array_merge(['provider_id' => 'fake', 'active' => true, 'backup_playlist_id' => 'BACKUP'], $attributes));
    Cache::flush();
    $fake = app(FakeMusicProvider::class);

    return [$party, $fake];
}

function playbackOf(string $trackId, bool $playing = true, int $progressMs = 1000, string $deviceId = 'device1', ?string $linkedFromId = null): PlaybackState
{
    return new PlaybackState($playing, $trackId, $linkedFromId, 200000, $progressMs, CarbonImmutable::now(), false, 'off', $deviceId, 'Test Speaker', PlaybackSource::Polling);
}

function nowPlaying(FakeMusicProvider $fake, string $trackId, bool $playing = true, int $progressMs = 1000, string $deviceId = 'device1', ?string $linkedFromId = null): void
{
    $fake->withTrack(new Track('fake', $trackId, "Name {$trackId}", 200000, false, true, new AlbumRef("album-{$trackId}", 'Test Album', null), [new ArtistRef('artist1', 'Test Artist')]));
    $fake->withPlayback(playbackOf($trackId, $playing, $progressMs, $deviceId, $linkedFromId));
}

beforeEach(function () {
    Cache::flush();
    $this->freezeTime();
});

it('records nothing when nothing is playing and no song is current', function () {
    [$party, $fake] = syncParty();

    app(PlaybackSync::class)->sync($party);

    expect($party->fresh()->song_id)->toBeNull()
        ->and(PlayedSong::query()->count())->toBe(0)
        ->and($fake->callsTo('getTrack'))->toBe([]);
});

it('sets the current song, start time, recent device and creates a Played Track when a track is playing', function () {
    [$party, $fake] = syncParty();
    nowPlaying($fake, 'PLAYED', true, 30000, 'speaker9');

    app(PlaybackSync::class)->sync($party);

    $fresh = $party->fresh();
    $played = PlayedSong::query()->sole();
    expect($fresh->song->provider_track_id)->toBe('PLAYED')
        ->and($fresh->song_started_at->toDateTimeString())->toBe(now()->subSeconds(30)->toDateTimeString())
        ->and($fresh->recent_device_id)->toBe('speaker9')
        ->and($played->party_id)->toBe($party->id)
        ->and($played->song_id)->toBe($fresh->song_id)
        ->and($played->played_at->toDateTimeString())->toBe(now()->subSeconds(30)->toDateTimeString());
});

it('creates the Song, Album and Artist rows from the playback item', function () {
    [$party, $fake] = syncParty();
    nowPlaying($fake, 'PLAYED');

    app(PlaybackSync::class)->sync($party);

    $song = Song::query()->where('provider_track_id', 'PLAYED')->sole();
    expect($song->name)->toBe('Name PLAYED')
        ->and($song->length)->toBe(200000)
        ->and($song->album->name)->toBe('Test Album')
        ->and($song->artists->pluck('name')->all())->toBe(['Test Artist']);
});

it('treats a paused track as current and creates the Played Track', function () {
    [$party, $fake] = syncParty();
    nowPlaying($fake, 'PAUSED', false, 5000);

    app(PlaybackSync::class)->sync($party);

    expect($party->fresh()->song->provider_track_id)->toBe('PAUSED')
        ->and(PlayedSong::query()->count())->toBe(1)
        ->and($party->getState()->status->isPlaying)->toBeFalse()
        ->and($party->getState()->status->active)->toBeTrue();
});

it('creates only one Played Track while the same song keeps playing', function () {
    [$party, $fake] = syncParty();
    nowPlaying($fake, 'SAME');

    app(PlaybackSync::class)->sync($party);
    app(PlaybackSync::class)->sync($party);
    app(PlaybackSync::class)->sync($party);

    expect(PlayedSong::query()->count())->toBe(1)
        ->and($fake->callsTo('getTrack'))->toHaveCount(1);
});

it('creates a new Played Track and moves the current song when the track changes', function () {
    [$party, $fake] = syncParty();
    nowPlaying($fake, 'FIRST');
    app(PlaybackSync::class)->sync($party);
    $this->travel(10)->seconds();
    nowPlaying($fake, 'SECOND');

    app(PlaybackSync::class)->sync($party);

    expect(PlayedSong::query()->count())->toBe(2)
        ->and($party->fresh()->song->provider_track_id)->toBe('SECOND')
        ->and($party->current()->song->provider_track_id)->toBe('SECOND');
});

it('clears the current song and start time when playback stops, without a Played Track', function (?PlaybackState $stopped) {
    [$party, $fake] = syncParty();
    nowPlaying($fake, 'GONE');
    app(PlaybackSync::class)->sync($party);
    $fake->withPlayback($stopped);

    app(PlaybackSync::class)->sync($party);

    $fresh = $party->fresh();
    expect($fresh->song_id)->toBeNull()
        ->and($fresh->song_started_at)->toBeNull()
        ->and(PlayedSong::query()->count())->toBe(1);
})->with([
    'no playback session' => [null],
    'playback with no item' => [PlaybackState::idle(PlaybackSource::Polling)],
]);

it('does not throw or change anything when playback has no item and no song is current', function () {
    [$party, $fake] = syncParty();
    $fake->withPlayback(PlaybackState::idle(PlaybackSource::Polling));

    expect(app(PlaybackSync::class)->sync($party))->toBeTrue()
        ->and($party->fresh()->song_id)->toBeNull()
        ->and(PlayedSong::query()->count())->toBe(0);
});

it('stores null as relinked_from when the playback carries no linked id', function () {
    [$party, $fake] = syncParty();
    nowPlaying($fake, 'PLAYED');

    app(PlaybackSync::class)->sync($party);

    expect(PlayedSong::query()->sole()->relinked_from)->toBeNull()
        ->and($fake->callsTo('getTrack'))->toBe([['PLAYED']]);
});

it('stores the playback linked id as relinked_from', function () {
    [$party, $fake] = syncParty();
    nowPlaying($fake, 'PLAYED', linkedFromId: 'RELINKED');

    app(PlaybackSync::class)->sync($party);

    expect(PlayedSong::query()->sole()->relinked_from)->toBe('RELINKED');
});

it('does not resolve a track through the provider when the Song already exists', function () {
    [$party, $fake] = syncParty();
    Song::factory()->create(['provider_id' => 'fake', 'provider_track_id' => 'PLAYED']);
    $fake->withPlayback(playbackOf('PLAYED'));

    app(PlaybackSync::class)->sync($party);

    expect($fake->callsTo('getTrack'))->toBe([])
        ->and($party->fresh()->song->provider_track_id)->toBe('PLAYED');
});

it('leaves the current song untouched when the playing track cannot be resolved', function () {
    [$party, $fake] = syncParty();
    $fake->withPlayback(playbackOf('UNKNOWN'));

    expect(app(PlaybackSync::class)->sync($party))->toBeTrue()
        ->and($party->fresh()->song_id)->toBeNull()
        ->and(PlayedSong::query()->count())->toBe(0);
});

it('links the Played Track to the Sent to Player Request for the same track', function () {
    [$party, $fake] = syncParty();
    $song = Song::factory()->create(['provider_id' => 'fake', 'provider_track_id' => 'PLAYED']);
    $request = UpcomingSong::factory()->for($party)->create(['song_id' => $song->id, 'queued_at' => now()->subMinute()]);
    nowPlaying($fake, 'PLAYED');

    app(PlaybackSync::class)->sync($party);

    expect(PlayedSong::query()->sole()->upcoming_song_id)->toBe($request->id);
});

it('links a Played Track to the Request for the relinked track id', function () {
    [$party, $fake] = syncParty();
    $song = Song::factory()->create(['provider_track_id' => 'RELINKED']);
    $request = UpcomingSong::factory()->for($party)->create(['song_id' => $song->id, 'queued_at' => now()->subMinute()]);
    nowPlaying($fake, 'PLAYED', linkedFromId: 'RELINKED');

    app(PlaybackSync::class)->sync($party);

    expect(PlayedSong::query()->sole()->upcoming_song_id)->toBe($request->id);
});

it('does not link a Played Track that already has a Played Track for the Request', function () {
    [$party, $fake] = syncParty();
    $song = Song::factory()->create(['provider_id' => 'fake', 'provider_track_id' => 'PLAYED']);
    $request = UpcomingSong::factory()->for($party)->create(['song_id' => $song->id, 'queued_at' => now()->subMinute()]);
    PlayedSong::factory()->create(['party_id' => $party->id, 'song_id' => $song->id, 'upcoming_song_id' => $request->id]);
    nowPlaying($fake, 'PLAYED');

    app(PlaybackSync::class)->sync($party);

    expect(PlayedSong::query()->orderByDesc('id')->first()->upcoming_song_id)->toBeNull();
});

it('does not attribute a play to a Request in another party or one never sent (E5 R14)', function () {
    [$party, $fake] = syncParty();
    $otherParty = Party::factory()->create(['active' => false]);
    $song = Song::factory()->create(['provider_id' => 'fake', 'provider_track_id' => 'PLAYED']);
    UpcomingSong::factory()->for($otherParty)->create(['song_id' => $song->id, 'queued_at' => now()->subMinute()]);
    UpcomingSong::factory()->for($party)->create(['song_id' => $song->id, 'queued_at' => null]);
    nowPlaying($fake, 'PLAYED');

    app(PlaybackSync::class)->sync($party);

    expect(PlayedSong::query()->where('party_id', $party->id)->sole()->upcoming_song_id)->toBeNull();
});

it('prefers the most recently queued Request when several exist for the same track (E5 R14)', function () {
    [$party, $fake] = syncParty();
    $song = Song::factory()->create(['provider_id' => 'fake', 'provider_track_id' => 'PLAYED']);
    UpcomingSong::factory()->for($party)->create(['song_id' => $song->id, 'queued_at' => now()->subMinutes(20)]);
    $newer = UpcomingSong::factory()->for($party)->create(['song_id' => $song->id, 'queued_at' => now()->subMinutes(2)]);
    nowPlaying($fake, 'PLAYED');

    app(PlaybackSync::class)->sync($party);

    expect(PlayedSong::query()->sole()->upcoming_song_id)->toBe($newer->id);
});

it('still updates the current song, sends nothing and stamps last_updated_at when the party is inactive', function () {
    [$party, $fake] = syncParty(['active' => false]);
    $song = Song::factory()->create(['provider_track_id' => 'WAITING']);
    UpcomingSong::factory()->for($party)->create(['song_id' => $song->id, 'queued_at' => null]);
    nowPlaying($fake, 'PLAYED');

    app(PlaybackSync::class)->sync($party);

    expect($party->fresh()->song->provider_track_id)->toBe('PLAYED')
        ->and(PlayedSong::query()->count())->toBe(1)
        ->and($fake->callsTo('sendToPlayer'))->toBe([])
        ->and($party->fresh()->last_updated_at->toDateTimeString())->toBe(now()->toDateTimeString());
});

it('stamps last_updated_at after a full active update', function () {
    [$party] = syncParty();

    app(PlaybackSync::class)->sync($party);

    expect($party->fresh()->last_updated_at->toDateTimeString())->toBe(now()->toDateTimeString());
});

it('skips the whole update when polling and the last update was under five seconds ago', function () {
    [$party, $fake] = syncParty(['poll' => true]);
    $party->last_updated_at = now()->subSeconds(4);
    $party->save();
    nowPlaying($fake, 'PLAYED');
    app(PlaybackSync::class)->sync($party);

    expect($fake->callsTo('currentPlayback'))->toBe([])
        ->and(PlayedSong::query()->count())->toBe(0);
});

it('runs the update when polling and the last update was more than five seconds ago', function () {
    [$party, $fake] = syncParty(['poll' => true]);
    $party->last_updated_at = now()->subSeconds(6);
    $party->save();
    nowPlaying($fake, 'PLAYED');

    app(PlaybackSync::class)->sync($party);

    expect(PlayedSong::query()->count())->toBe(1);
});

it('ignores the debounce window when the party is not polling', function () {
    [$party, $fake] = syncParty(['poll' => false]);
    $party->last_updated_at = now();
    $party->save();
    nowPlaying($fake, 'PLAYED');

    app(PlaybackSync::class)->sync($party);

    expect(PlayedSong::query()->count())->toBe(1);
});

it('reports the current song, queued next request and player status through getState', function () {
    [$party, $fake] = syncParty();
    $song = Song::factory()->create(['provider_track_id' => 'NEXT']);
    UpcomingSong::factory()->for($party)->create(['song_id' => $song->id, 'queued_at' => now()]);
    nowPlaying($fake, 'PLAYED', true, 4000);

    app(PlaybackSync::class)->sync($party);
    $state = $party->fresh()->getState();

    expect($state->now['provider_track_id'])->toBe('PLAYED')
        ->and($state->next['provider_track_id'])->toBe('NEXT')
        ->and($state->status->active)->toBeTrue()
        ->and($state->status->isPlaying)->toBeTrue()
        ->and($state->status->progress)->toBe(4000)
        ->and($state->status->length)->toBe(200000)
        ->and($state->status->device->id)->toBe('device1');
});

it('applies a pushed playback state: current track, Played Track and a single broadcast', function () {
    [$party, $fake] = syncParty();
    $fake->withTrack(new Track('fake', 'PUSHED', 'Pushed', 200000, false, true, new AlbumRef('album-p', 'Test Album', null), [new ArtistRef('artist1', 'Test Artist')]));
    Event::fake([PartyUpdatedEvent::class]);

    expect(app(PlaybackSync::class)->apply($party, playbackOf('PUSHED', true, 30000, 'speaker9')))->toBeTrue();

    $fresh = $party->fresh();
    expect($fresh->song->provider_track_id)->toBe('PUSHED')
        ->and($fresh->recent_device_id)->toBe('speaker9')
        ->and($fresh->song_started_at->toDateTimeString())->toBe(now()->subSeconds(30)->toDateTimeString())
        ->and(PlayedSong::query()->sole()->song_id)->toBe($fresh->song_id)
        ->and($fake->callsTo('currentPlayback'))->toBe([])
        ->and($party->getState()->status->isPlaying)->toBeTrue();
    Event::assertDispatchedTimes(PartyUpdatedEvent::class, 1);
});

it('does not record a second Played Track when the same pushed state is applied twice', function () {
    [$party, $fake] = syncParty();
    nowPlaying($fake, 'PUSHED');

    app(PlaybackSync::class)->apply($party, playbackOf('PUSHED'));
    app(PlaybackSync::class)->apply($party, playbackOf('PUSHED'));

    expect(PlayedSong::query()->count())->toBe(1);
});

it('does not apply a pushed state while another sync holds the lock', function () {
    [$party, $fake] = syncParty();
    nowPlaying($fake, 'PUSHED');
    $lock = Cache::lock("party-sync:{$party->id}", 30);
    $lock->get();

    expect(app(PlaybackSync::class)->apply($party, playbackOf('PUSHED')))->toBeFalse()
        ->and(PlayedSong::query()->count())->toBe(0);

    $lock->release();
});
