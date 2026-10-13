<?php

use App\Models\Party;
use App\Models\PlayedSong;
use App\Models\Song;
use App\Models\UpcomingSong;
use Illuminate\Support\Facades\Cache;
use Tests\Support\FakeSpotify;

function syncParty(array $attributes = []): array
{
    $fake = FakeSpotify::install();
    $party = Party::factory()->create(array_merge(['active' => true, 'backup_playlist_id' => 'BACKUP'], $attributes));
    Cache::flush();
    $fake->calls = [];

    return [$party, $fake];
}

function nowPlaying(FakeSpotify $fake, string $trackId, bool $playing = true, int $progressMs = 1000, string $deviceId = 'device1'): void
{
    $playback = FakeSpotify::playback(FakeSpotify::track($trackId, "Name {$trackId}"), $playing, $progressMs);
    $playback->device = FakeSpotify::device($deviceId);
    $fake->respond('getMyCurrentPlaybackInfo', $playback);
}

beforeEach(function () {
    Cache::flush();
    $this->freezeTime();
});

it('records nothing when nothing is playing and no song is current', function () {
    [$party, $fake] = syncParty();

    $party->updateState();

    expect($party->fresh()->song_id)->toBeNull()
        ->and(PlayedSong::query()->count())->toBe(0)
        ->and($fake->callsTo('getTrack'))->toBe([]);
});

it('sets the current song, start time, recent device and creates a Played Track when a track is playing', function () {
    [$party, $fake] = syncParty();
    nowPlaying($fake, 'PLAYED', true, 30000, 'speaker9');

    $party->updateState();

    $fresh = $party->fresh();
    $played = PlayedSong::query()->sole();
    expect($fresh->song->spotify_id)->toBe('PLAYED')
        ->and($fresh->song_started_at->toDateTimeString())->toBe(now()->subSeconds(30)->toDateTimeString())
        ->and($fresh->recent_device_id)->toBe('speaker9')
        ->and($played->party_id)->toBe($party->id)
        ->and($played->song_id)->toBe($fresh->song_id)
        ->and($played->played_at->toDateTimeString())->toBe(now()->subSeconds(30)->toDateTimeString());
});

it('creates the Song, Album and Artist rows from the playback item', function () {
    [$party, $fake] = syncParty();
    nowPlaying($fake, 'PLAYED');

    $party->updateState();

    $song = Song::query()->where('spotify_id', 'PLAYED')->sole();
    expect($song->name)->toBe('Name PLAYED')
        ->and($song->length)->toBe(200000)
        ->and($song->album->name)->toBe('Test Album')
        ->and($song->artists->pluck('name')->all())->toBe(['Test Artist']);
});

it('treats a paused track as current and creates the Played Track', function () {
    [$party, $fake] = syncParty();
    nowPlaying($fake, 'PAUSED', false, 5000);

    $party->updateState();

    expect($party->fresh()->song->spotify_id)->toBe('PAUSED')
        ->and(PlayedSong::query()->count())->toBe(1)
        ->and($party->getState()->status->isPlaying)->toBeFalse()
        ->and($party->getState()->status->active)->toBeTrue();
});

it('creates only one Played Track while the same song keeps playing', function () {
    [$party, $fake] = syncParty();
    nowPlaying($fake, 'SAME');

    $party->updateState();
    $party->updateState();
    $party->updateState();

    expect(PlayedSong::query()->count())->toBe(1)
        ->and($fake->callsTo('getTrack'))->toHaveCount(1);
});

it('creates a new Played Track and moves the current song when the track changes', function () {
    [$party, $fake] = syncParty();
    nowPlaying($fake, 'FIRST');
    $party->updateState();
    $this->travel(10)->seconds();
    nowPlaying($fake, 'SECOND');

    $party->updateState();

    expect(PlayedSong::query()->count())->toBe(2)
        ->and($party->fresh()->song->spotify_id)->toBe('SECOND')
        ->and($party->current()->song->spotify_id)->toBe('SECOND');
});

it('clears the current song and start time when playback stops, without a Played Track', function (?object $stopped) {
    [$party, $fake] = syncParty();
    nowPlaying($fake, 'GONE');
    $party->updateState();
    $fake->respond('getMyCurrentPlaybackInfo', $stopped);

    $party->updateState();

    $fresh = $party->fresh();
    expect($fresh->song_id)->toBeNull()
        ->and($fresh->song_started_at)->toBeNull()
        ->and(PlayedSong::query()->count())->toBe(1);
})->with([
    'no playback session' => [null],
    'playback with no item' => [FakeSpotify::playback(null, false, 0)],
]);

it('throws a TypeError when playback has no item and no song is current (not covered by E5)', function () {
    [$party, $fake] = syncParty();
    $fake->respond('getMyCurrentPlaybackInfo', FakeSpotify::playback(null, false, 0));

    expect(fn () => $party->updateState())->toThrow(TypeError::class);
});

it('stores the id returned by getTrack as relinked_from even when it equals the played id', function () {
    [$party, $fake] = syncParty();
    nowPlaying($fake, 'PLAYED');
    $fake->respond('getTrack', FakeSpotify::track('PLAYED'));

    $party->updateState();

    expect(PlayedSong::query()->sole()->relinked_from)->toBe('PLAYED');
});

it('stores a different relinked track id as relinked_from and asks Spotify for the literal market list', function () {
    [$party, $fake] = syncParty();
    nowPlaying($fake, 'PLAYED');
    $fake->respond('getTrack', FakeSpotify::track('RELINKED'));

    $party->updateState();

    expect(PlayedSong::query()->sole()->relinked_from)->toBe('RELINKED')
        ->and($fake->callsTo('getTrack'))->toBe([['PLAYED', ['market']]]);
});

it('caches the relinked track id for a day per party and played id', function () {
    [$party, $fake] = syncParty();
    nowPlaying($fake, 'PLAYED');
    $fake->respond('getTrack', FakeSpotify::track('RELINKED'));

    $party->updateState();

    expect(Cache::get("party.{$party->id}.relinkedtrackid.PLAYED"))->toBe('RELINKED');

    nowPlaying($fake, 'OTHER');
    $party->updateState();
    nowPlaying($fake, 'PLAYED');
    $party->updateState();

    expect($fake->callsTo('getTrack'))->toBe([['PLAYED', ['market']], ['OTHER', ['market']]])
        ->and(PlayedSong::query()->count())->toBe(3);
});

it('links the Played Track to the Sent to Player Request for the same track', function () {
    [$party, $fake] = syncParty();
    $song = Song::factory()->create(['spotify_id' => 'PLAYED']);
    $request = UpcomingSong::factory()->for($party)->create(['song_id' => $song->id, 'queued_at' => now()->subMinute()]);
    nowPlaying($fake, 'PLAYED');

    $party->updateState();

    expect(PlayedSong::query()->sole()->upcoming_song_id)->toBe($request->id);
});

it('links a Played Track to the Request for the relinked track id', function () {
    [$party, $fake] = syncParty();
    $song = Song::factory()->create(['spotify_id' => 'RELINKED']);
    $request = UpcomingSong::factory()->for($party)->create(['song_id' => $song->id, 'queued_at' => now()->subMinute()]);
    nowPlaying($fake, 'PLAYED');
    $fake->respond('getTrack', FakeSpotify::track('RELINKED'));

    $party->updateState();

    expect(PlayedSong::query()->sole()->upcoming_song_id)->toBe($request->id);
});

it('does not link a Played Track that already has a Played Track for the Request', function () {
    [$party, $fake] = syncParty();
    $song = Song::factory()->create(['spotify_id' => 'PLAYED']);
    $request = UpcomingSong::factory()->for($party)->create(['song_id' => $song->id, 'queued_at' => now()->subMinute()]);
    PlayedSong::factory()->create(['party_id' => $party->id, 'song_id' => $song->id, 'upcoming_song_id' => $request->id]);
    nowPlaying($fake, 'PLAYED');

    $party->updateState();

    expect(PlayedSong::query()->orderByDesc('id')->first()->upcoming_song_id)->toBeNull();
});

it('does not attribute a play to a Request in another party or one never sent (E5 R14)', function () {
    [$party, $fake] = syncParty();
    $otherParty = Party::factory()->create(['active' => false]);
    $song = Song::factory()->create(['spotify_id' => 'PLAYED']);
    UpcomingSong::factory()->for($otherParty)->create(['song_id' => $song->id, 'queued_at' => now()->subMinute()]);
    UpcomingSong::factory()->for($party)->create(['song_id' => $song->id, 'queued_at' => null]);
    nowPlaying($fake, 'PLAYED');

    $party->updateState();

    expect(PlayedSong::query()->where('party_id', $party->id)->sole()->upcoming_song_id)->toBeNull();
});

it('prefers the most recently queued Request when several exist for the same track (E5 R14)', function () {
    [$party, $fake] = syncParty();
    $song = Song::factory()->create(['spotify_id' => 'PLAYED']);
    UpcomingSong::factory()->for($party)->create(['song_id' => $song->id, 'queued_at' => now()->subMinutes(20)]);
    $newer = UpcomingSong::factory()->for($party)->create(['song_id' => $song->id, 'queued_at' => now()->subMinutes(2)]);
    nowPlaying($fake, 'PLAYED');

    $party->updateState();

    expect(PlayedSong::query()->sole()->upcoming_song_id)->toBe($newer->id);
});

it('still updates the current song but sends nothing when the party is inactive', function () {
    [$party, $fake] = syncParty(['active' => false]);
    $song = Song::factory()->create(['spotify_id' => 'WAITING']);
    UpcomingSong::factory()->for($party)->create(['song_id' => $song->id, 'queued_at' => null]);
    nowPlaying($fake, 'PLAYED');

    $party->updateState();

    expect($party->fresh()->song->spotify_id)->toBe('PLAYED')
        ->and(PlayedSong::query()->count())->toBe(1)
        ->and($fake->callsTo('queue'))->toBe([])
        ->and($fake->callsTo('getMyDevices'))->toBe([])
        ->and($party->fresh()->last_updated_at)->toBeNull();
});

it('stamps last_updated_at after a full active update', function () {
    [$party] = syncParty();

    $party->updateState();

    expect($party->fresh()->last_updated_at->toDateTimeString())->toBe(now()->toDateTimeString());
});

it('skips the whole update when polling and the last update was under five seconds ago', function () {
    [$party, $fake] = syncParty(['poll' => true]);
    $party->last_updated_at = now()->subSeconds(4);
    $party->save();
    nowPlaying($fake, 'PLAYED');
    $fake->calls = [];

    $party->updateState();

    expect($fake->calls)->toBe([])
        ->and(PlayedSong::query()->count())->toBe(0);
});

it('runs the update when polling and the last update was more than five seconds ago', function () {
    [$party, $fake] = syncParty(['poll' => true]);
    $party->last_updated_at = now()->subSeconds(6);
    $party->save();
    nowPlaying($fake, 'PLAYED');

    $party->updateState();

    expect(PlayedSong::query()->count())->toBe(1);
});

it('ignores the debounce window when the party is not polling', function () {
    [$party, $fake] = syncParty(['poll' => false]);
    $party->last_updated_at = now();
    $party->save();
    nowPlaying($fake, 'PLAYED');

    $party->updateState();

    expect(PlayedSong::query()->count())->toBe(1);
});

it('reports the current song, queued next request and player status through getState', function () {
    [$party, $fake] = syncParty();
    $song = Song::factory()->create(['spotify_id' => 'NEXT']);
    UpcomingSong::factory()->for($party)->create(['song_id' => $song->id, 'queued_at' => now()]);
    nowPlaying($fake, 'PLAYED', true, 4000);

    $party->updateState();
    $state = $party->fresh()->getState();

    expect($state->now['spotify_id'])->toBe('PLAYED')
        ->and($state->next['spotify_id'])->toBe('NEXT')
        ->and($state->status->active)->toBeTrue()
        ->and($state->status->isPlaying)->toBeTrue()
        ->and($state->status->progress)->toBe(4000)
        ->and($state->status->length)->toBe(200000)
        ->and($state->status->device->id)->toBe('device1');
});
