<?php

use App\Enums\Purpose;
use App\Exceptions\NoActiveDeviceException;
use App\Exceptions\ProviderPremiumRequiredException;
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

it('counts only unique queued tracks after the playing one as the queue depth', function (array $queued, int $depth) {
    $api = FakeSpotify::install();
    $api->respond('getMyQueue', (object) ['currently_playing' => null, 'queue' => array_map(fn (string $id) => FakeSpotify::track($id), $queued)]);

    expect(spotifyProvider($api)->queueDepth())->toBe($depth);
})->with([
    'empty queue' => [[], -1],
    'one unique track' => [['X'], 0],
    'padded with context copies' => [array_fill(0, 20, 'CTX'), 0],
    'two unique tracks' => [['X', 'Y', 'X', 'Y'], 1],
]);

it('reserves only the context playlist track', function () {
    $provider = spotifyProvider(FakeSpotify::install());

    expect($provider->isReservedTrack(SpotifyMusicProvider::CONTEXT_PLAYLIST_TRACK_ID))->toBeTrue()
        ->and($provider->isReservedTrack('abc'))->toBeFalse();
});

function ownedPlaylistPayload(string $id = 'ctx', string $name = 'Music Party Context', string $owner = 'me1', int $total = 0): stdClass
{
    return (object) ['id' => $id, 'name' => $name, 'owner' => (object) ['id' => $owner, 'display_name' => 'Owner'], 'tracks' => (object) ['total' => $total]];
}

it('adds the track to the context playlist, plays it by position, then empties the playlist', function () {
    $api = FakeSpotify::install();
    $provider = spotifyProvider($api);

    $provider->playInContext('ctx', mappedTrack('abc'), 'dev1', 5000);
    $provider->playInContext('ctx', mappedTrack('abc'));

    $context = ['context_uri' => 'spotify:playlist:ctx', 'offset' => ['position' => 0]];
    $order = array_map(fn (array $call): string => $call['method'], $api->calls);
    expect(array_slice($order, 0, 3))->toBe(['replacePlaylistTracks', 'play', 'replacePlaylistTracks'])
        ->and($api->callsTo('replacePlaylistTracks'))->toBe([['ctx', ['abc']], ['ctx', []], ['ctx', ['abc']], ['ctx', []]])
        ->and($api->callsTo('play'))->toBe([
            ['dev1', $context + ['position_ms' => 5000]],
            ['', $context + ['position_ms' => 0]],
        ]);
});

it('still empties the context playlist when the play command fails', function () {
    $api = FakeSpotify::install()->respond('play', new SpotifyWebAPIException('No active device', 404));

    expect(fn () => spotifyProvider($api)->playInContext('ctx', mappedTrack('abc')))->toThrow(NoActiveDeviceException::class);

    expect($api->callsTo('replacePlaylistTracks'))->toBe([['ctx', ['abc']], ['ctx', []]]);
});

it('creates a private playlist through /me/playlists without leaking the library deprecation notice', function () {
    $api = FakeSpotify::install();
    $leaked = [];
    set_error_handler(function (int $level, string $message) use (&$leaked): bool {
        $leaked[] = $message;

        return true;
    }, E_USER_DEPRECATED);

    try {
        $playlist = spotifyProvider($api)->createPrivatePlaylist('Music Party Context', 'Leave it empty.');
    } finally {
        restore_error_handler();
    }

    expect($api->callsTo('createPlaylist'))->toBe([[['name' => 'Music Party Context', 'description' => 'Leave it empty.', 'public' => false]]])
        ->and($playlist->id)->toBe('newpl')
        ->and($leaked)->toBe([]);
});

it('clears a playlist with an empty replace', function () {
    $api = FakeSpotify::install();

    spotifyProvider($api)->clearPlaylist('ctx');

    expect($api->callsTo('replacePlaylistTracks'))->toBe([['ctx', []]]);
});

it('only reports playlists the linked account owns', function (mixed $response, ?string $expected) {
    $api = FakeSpotify::install()->respond('getPlaylist', $response());

    expect(spotifyProvider($api)->ownedPlaylist('ctx')?->id)->toBe($expected);
})->with([
    'own playlist' => [fn () => ownedPlaylistPayload(), 'ctx'],
    'foreign playlist' => [fn () => ownedPlaylistPayload(owner: 'someone-else'), null],
    'missing playlist' => [fn () => new SpotifyWebAPIException('Not found', 404), null],
    'forbidden playlist' => [fn () => new SpotifyWebAPIException('Forbidden', 403), null],
]);

it('finds the first owned playlist with an exact name', function () {
    $api = FakeSpotify::install()->respond('getMyPlaylists', (object) ['next' => null, 'items' => [
        ownedPlaylistPayload('a', 'Music Party Context', 'someone-else'),
        ownedPlaylistPayload('b', 'Music Party Context Copy'),
        ownedPlaylistPayload('c', 'Music Party Context'),
    ]]);

    expect(spotifyProvider($api)->findOwnedPlaylistByName('Music Party Context')?->id)->toBe('c');
});

it('maps a Spotify Premium-only refusal to a dedicated exception', function () {
    $api = FakeSpotify::install();
    $refusal = new SpotifyWebAPIException('Player command failed: Premium required', 403);
    $refusal->setReason('PREMIUM_REQUIRED');
    $api->respond('pause', $refusal);

    spotifyProvider($api)->pause();
})->throws(ProviderPremiumRequiredException::class);

it('names playback contexts and caches the lookup', function () {
    Cache::flush();
    $api = FakeSpotify::install()->respond('getPlaylist', (object) ['name' => 'Road Trip']);
    $provider = spotifyProvider($api);

    expect($provider->contextName('spotify:user:abc:collection'))->toBe('Liked Songs')
        ->and($provider->contextName('spotify:playlist:pl1'))->toBe('Road Trip')
        ->and($provider->contextName('spotify:playlist:pl1'))->toBe('Road Trip')
        ->and($provider->contextName('spotify:show:xyz'))->toBeNull()
        ->and($api->callsTo('getPlaylist'))->toHaveCount(1)
        ->and($provider->playlistContextUri('pl1'))->toBe('spotify:playlist:pl1');
});
