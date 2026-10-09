<?php

use App\Domain\Music\Accounts\HostAccountTokens;
use App\Domain\Music\Capability;
use App\Domain\Music\Contracts\MusicProvider;
use App\Domain\Music\Data\AlbumData;
use App\Domain\Music\Data\ArtistData;
use App\Domain\Music\Data\TrackData;
use App\Domain\Music\Exceptions\ProviderTemporaryFailure;
use App\Domain\Music\Exceptions\ProviderUnavailableException;
use App\Domain\Music\Exceptions\UnsupportedCapability;
use App\Domain\Music\Providers\SpotifyMusicProvider;
use App\Domain\Music\Testing\FakeMusicProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\Spotify\SpotifyFake;

dataset('providers', [
    'fake' => [fn (): array => [
        FakeMusicProvider::withDefaultCatalogue(),
        fn (MusicProvider $p) => $p->failNextWith(new ProviderUnavailableException('no credential')),
        fn (MusicProvider $p) => $p->failNextWith(new ProviderTemporaryFailure),
        fn (MusicProvider $p, int $seconds) => $p->rateLimitNext($seconds),
    ]],
    'spotify' => [function (): array {
        config(['services.spotify' => ['client_id' => 'id', 'client_secret' => 'secret', 'market' => 'US']]);
        Cache::flush();
        $control = new stdClass;
        SpotifyFake::catalogue($control);

        return [
            new SpotifyMusicProvider(new HostAccountTokens),
            fn () => config(['services.spotify.client_id' => null]),
            fn () => $control->next = Http::response('', 503),
            fn (MusicProvider $p, int $seconds) => $control->next = Http::response('', 429, ['Retry-After' => (string) $seconds]),
        ];
    }],
]);

dataset('playlist providers', [
    'fake' => [fn (): array => [FakeMusicProvider::withDefaultCatalogue()]],
]);

it('pages search results within bounds', function (array $fixture) {
    [$provider] = $fixture;

    $first = $provider->search('song', 1, 0);
    $second = $provider->search('song', 1, 1);
    $beyond = $provider->search('song', 10, 50);

    expect($first->total)->toBe(2)
        ->and($first->items)->toHaveCount(1)
        ->and($first->limit)->toBe(1)
        ->and($first->offset)->toBe(0)
        ->and($second->items)->toHaveCount(1)
        ->and($second->items[0]->providerTrackId)->not->toBe($first->items[0]->providerTrackId)
        ->and($beyond->items)->toBe([])
        ->and($beyond->total)->toBe(2);
})->with('providers');

it('searches case-insensitively over name and artist', function (array $fixture) {
    [$provider] = $fixture;

    expect($provider->search('ALPHA', 10, 0)->items)->toHaveCount(1)
        ->and($provider->search('other band', 10, 0)->items)->toHaveCount(1)
        ->and($provider->search('zzz-no-match', 10, 0)->total)->toBe(0);
})->with('providers');

it('returns a track on hit and null on miss', function (array $fixture) {
    [$provider] = $fixture;

    $track = $provider->getTrack('track-1');

    expect($track)->toBeInstanceOf(TrackData::class)
        ->and($track->providerId)->toBe($provider->id())
        ->and($track->artists[0])->toBeInstanceOf(ArtistData::class)
        ->and($track->album)->toBeInstanceOf(AlbumData::class)
        ->and($provider->getTrack('missing'))->toBeNull();
})->with('providers');

it('returns tracks without isrc or cover art with empty fields', function (array $fixture) {
    [$provider] = $fixture;

    $track = $provider->getTrack('track-3');

    expect($track)->not->toBeNull()
        ->and($track->isrc)->toBeNull()
        ->and($track->coverArtUrls)->toBe([]);
})->with('providers');

it('reports playability for the market', function (array $fixture) {
    [$provider] = $fixture;

    expect($provider->getTrack('track-1')->playableInMarket)->toBeTrue()
        ->and($provider->getTrack('track-4')->playableInMarket)->toBeFalse();
})->with('providers');

it('lists playlists and their tracks', function (array $fixture) {
    [$provider] = $fixture;

    $playlists = $provider->playlists('host-account-1');
    $tracks = $provider->playlistTracks($playlists[0]->id, 'host-account-1');

    expect($playlists)->not->toBeEmpty()
        ->and($tracks)->toHaveCount($playlists[0]->trackCount)
        ->and($tracks[0])->toBeInstanceOf(TrackData::class)
        ->and($provider->playlistTracks('unknown', 'host-account-1'))->toBe([]);
})->with('playlist providers');

it('declares playlist write support and appends', function (array $fixture) {
    [$provider] = $fixture;

    expect($provider->supports(Capability::PlaylistWrite))->toBeTrue();

    $provider->appendToPlaylist('playlist-1', ['track-2'], 'host-account-1');

    expect($provider->playlistTracks('playlist-1', 'host-account-1'))->toHaveCount(3);
})->with('playlist providers');

it('refuses to append when playlist write is unsupported', function () {
    $provider = new FakeMusicProvider(capabilities: []);

    expect($provider->supports(Capability::PlaylistWrite))->toBeFalse();

    $provider->appendToPlaylist('playlist-1', ['track-1'], 'host-account-1');
})->throws(UnsupportedCapability::class, 'playlist-write is unsupported by Music Provider');

it('surfaces a temporary failure once then recovers', function (array $fixture) {
    [$provider, , $failTemporarily] = $fixture;
    $failTemporarily($provider);

    expect(fn () => $provider->search('song', 10, 0))->toThrow(ProviderTemporaryFailure::class)
        ->and($provider->search('song', 10, 0)->total)->toBe(2);
})->with('providers');

it('surfaces rate limiting with the advised back-off', function (array $fixture) {
    [$provider, , , $rateLimit] = $fixture;
    $rateLimit($provider, 30);

    try {
        $provider->getTrack('track-1');
        $this->fail('Expected a temporary failure.');
    } catch (ProviderTemporaryFailure $e) {
        expect($e->retryAfterSeconds)->toBe(30);
    }
})->with('providers');

it('reports unavailable when the search credential is missing', function (array $fixture) {
    [$provider, $makeUnavailable] = $fixture;
    $makeUnavailable($provider);

    $provider->search('song', 10, 0);
})->with('providers')->throws(ProviderUnavailableException::class);
