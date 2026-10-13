<?php

use App\Enums\Purpose;
use App\Models\User;
use App\Services\Music\Data\Track;
use App\Services\Music\Spotify\SpotifyMapper;
use App\Services\Music\Spotify\SpotifyMusicProvider;
use App\Services\Spotify\SpotifyGateway;
use SpotifyWebAPI\SpotifyWebAPI;
use Tests\Support\FakeSpotify;

function spotifyProvider(FakeSpotify $api): SpotifyMusicProvider
{
    $gateway = new class($api) extends SpotifyGateway
    {
        public function __construct(protected FakeSpotify $api) {}

        public function client(User $user): ?SpotifyWebAPI
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
    $provider->play(null, null);

    expect($api->callsTo('queue'))->toBe([['abc']])
        ->and($api->callsTo('play'))->toBe([['dev1', ['uris' => ['spotify:track:abc']]], ['', []]]);
});

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
