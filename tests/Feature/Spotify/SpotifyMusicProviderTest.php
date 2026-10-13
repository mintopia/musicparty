<?php

use App\Enums\Purpose;
use App\Exceptions\NoActiveDeviceException;
use App\Models\User;
use App\Services\Music\Data\Track;
use App\Services\Music\Spotify\SpotifyMapper;
use App\Services\Music\Spotify\SpotifyMusicProvider;
use App\Services\Spotify\SpotifyGateway;
use Illuminate\Support\Facades\Cache;
use SpotifyWebAPI\SpotifyWebAPI;
use SpotifyWebAPI\SpotifyWebAPIException;
use Tests\Support\FakeSpotify;

function spotifyProvider(FakeSpotify $api): SpotifyMusicProvider
{
    $gateway = new class($api) extends SpotifyGateway
    {
        public function __construct(protected FakeSpotify $api) {}

        public function client(User $user): SpotifyWebAPI
        {
            return $this->api;
        }
    };

    return new SpotifyMusicProvider($gateway, User::factory()->make(['market' => 'GB']), Purpose::Control);
}

function mappedTrack(string $id = 'abc'): Track
{
    return SpotifyMapper::track(FakeSpotify::track($id));
}

it('sends tracks to the player by id and plays with a track uri', function () {
    $api = FakeSpotify::install();
    $provider = spotifyProvider($api);

    $provider->sendToPlayer(mappedTrack('abc'));
    $provider->play('dev1', mappedTrack('abc'));
    $provider->play();

    expect($api->callsTo('queue'))->toBe([['abc']])
        ->and($api->callsTo('play'))->toBe([['dev1', ['uris' => ['spotify:track:abc']]], ['', []]]);
});

it('transfers playback with changeMyDevice and maps a player 404 to NoActiveDeviceException', function () {
    $api = FakeSpotify::install();
    $provider = spotifyProvider($api);

    $provider->transferPlayback('dev1');
    expect($api->callsTo('changeMyDevice'))->toBe([[['device_ids' => ['dev1']]]]);

    $api->respond('queue', new SpotifyWebAPIException('Player command failed: No active device found', 404));
    $provider->sendToPlayer(mappedTrack('abc'));
})->throws(NoActiveDeviceException::class);

it('maps current playback and handles an idle player', function () {
    $api = FakeSpotify::install();
    $provider = spotifyProvider($api);

    expect($provider->currentPlayback())->toBeNull();

    $api->respond('getMyCurrentPlaybackInfo', FakeSpotify::playback(FakeSpotify::track('now')));
    $state = $provider->currentPlayback();

    expect($state->trackId)->toBe('now')->and($state->deviceId)->toBe('device1')->and($state->progressMs)->toBe(1000);
});

it('maps playlist tracks, skipping empty items', function () {
    $api = FakeSpotify::install()->withBackupPlaylist(['one', 'two']);

    $tracks = spotifyProvider($api)->getPlaylistTracks('PL');

    expect(array_map(fn (Track $t) => $t->id, $tracks))->toBe(['one', 'two']);
});

it('maps search results to a page', function () {
    $api = FakeSpotify::install()->respond('search', (object) ['tracks' => (object) ['items' => [FakeSpotify::track('s1')], 'total' => 41]]);

    $page = spotifyProvider($api)->searchTracks('q', 3, 20);

    expect($page->total)->toBe(41)->and($page->items[0]->id)->toBe('s1')
        ->and($api->callsTo('search')[0][2])->toMatchArray(['market' => 'GB', 'offset' => 40]);
});

it('transports through the control client', function () {
    $api = FakeSpotify::install();
    $provider = spotifyProvider($api);

    $provider->pause();
    $provider->next();
    $provider->previous();
    $provider->seek(5000);

    expect($api->callsTo('seek'))->toBe([[['position_ms' => 5000]]])
        ->and($provider->devices()[0]->name)->toBe('Test Speaker');
});

it('maps and caches album and artist metadata so repeat lookups skip the API', function () {
    Cache::flush();
    $api = FakeSpotify::install()
        ->respond('getAlbum', (object) ['id' => 'alb1', 'name' => 'Album One', 'images' => [], 'artists' => [FakeSpotify::artist()], 'total_tracks' => 1, 'tracks' => (object) ['items' => [FakeSpotify::track('t1')]]])
        ->respond('getArtist', (object) ['id' => 'artist1', 'name' => 'Test Artist', 'images' => []]);
    $provider = spotifyProvider($api);

    $album = $provider->getAlbum('alb1');
    $provider->getAlbum('alb1');
    $artist = $provider->getArtist('artist1');
    $provider->getArtist('artist1');

    expect($album->ref->name)->toBe('Album One')->and($album->tracks)->toHaveCount(1)
        ->and($artist->ref->id)->toBe('artist1')
        ->and($api->callsTo('getAlbum'))->toHaveCount(1)
        ->and($api->callsTo('getArtist'))->toHaveCount(1);

    $this->travel(61)->minutes();
    expect($provider->getAlbum('alb1'))->not->toBeNull()
        ->and($api->callsTo('getAlbum'))->toHaveCount(2);
});

it('maps artist top tracks and paged artist albums', function () {
    $api = FakeSpotify::install()
        ->respond('getArtistTopTracks', (object) ['tracks' => [FakeSpotify::track('a'), FakeSpotify::track('b')]])
        ->respond('getArtistAlbums', (object) ['items' => [(object) ['id' => 'alb1', 'name' => 'One', 'images' => []]], 'total' => 7]);
    $provider = spotifyProvider($api);

    $tracks = $provider->getArtistTopTracks('artist1');
    $albums = $provider->getArtistAlbums('artist1', 2, 5);

    expect(array_map(fn (Track $t) => $t->id, $tracks))->toBe(['a', 'b'])
        ->and($albums->total)->toBe(7)->and($albums->items[0]->id)->toBe('alb1')
        ->and($api->callsTo('getArtistAlbums')[0][1])->toMatchArray(['market' => 'GB', 'limit' => 5, 'offset' => 5]);
});
