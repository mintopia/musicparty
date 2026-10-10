<?php

use App\Domain\Music\Exceptions\ProviderTemporaryFailure;
use App\Domain\Music\Exceptions\ProviderUnavailableException;
use App\Domain\Music\Providers\Spotify\HostAccountTokens;
use App\Domain\Music\Providers\Spotify\SpotifyApi;
use App\Domain\Music\Providers\SpotifyMusicProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\Spotify\SpotifyFake;

beforeEach(function () {
    config(['services.spotify' => ['client_id' => 'id', 'client_secret' => 'secret', 'market' => 'GB']]);
    Cache::flush();
    Http::preventStrayRequests();
    $this->provider = new SpotifyMusicProvider(new SpotifyApi(new HostAccountTokens));
});

function fakeSpotify(array $api): void
{
    Http::fake(['accounts.spotify.com/*' => Http::response(SpotifyFake::fixture('token'))] + $api);
}

function tokenRequests(): int
{
    return Http::recorded(fn (Request $r): bool => str_contains($r->url(), 'accounts.spotify.com'))->count();
}

it('identifies itself', function () {
    expect($this->provider->id())->toBe('spotify');
});

it('searches and maps tracks with market and basic auth token request', function () {
    fakeSpotify(['api.spotify.com/v1/search*' => Http::response(SpotifyFake::fixture('search'))]);

    $page = $this->provider->search('song', 2, 0);

    expect($page->total)->toBe(2)
        ->and($page->items)->toHaveCount(2)
        ->and($page->items[0]->providerId)->toBe('spotify')
        ->and($page->items[0]->providerTrackId)->toBe('track-1')
        ->and($page->items[0]->isrc)->toBe('ISRC0000001')
        ->and($page->items[0]->artists[0]->id)->toBe('artist-1')
        ->and($page->items[0]->album->id)->toBe('album-1')
        ->and($page->items[0]->durationMs)->toBe(180000)
        ->and($page->items[0]->coverArtUrls)->toBe(['https://i.scdn.co/image/one-640', 'https://i.scdn.co/image/one-300'])
        ->and($page->items[1]->explicit)->toBeTrue();

    Http::assertSent(fn (Request $r): bool => str_contains($r->url(), '/v1/search')
        && $r['type'] === 'track' && $r['market'] === 'GB' && $r['q'] === 'song'
        && $r->hasHeader('Authorization', 'Bearer token-abc'));
    Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'accounts.spotify.com/api/token')
        && $r['grant_type'] === 'client_credentials'
        && $r->hasHeader('Authorization', 'Basic '.base64_encode('id:secret')));
});

it('omits the market param when none is configured', function () {
    config(['services.spotify.market' => null]);
    fakeSpotify(['api.spotify.com/v1/tracks/*' => Http::response(SpotifyFake::fixture('track'))]);

    $this->provider->getTrack('track-1');

    Http::assertSent(fn (Request $r): bool => str_contains($r->url(), '/v1/tracks/') && ! str_contains($r->url(), 'market'));
});

it('maps track edge fixtures', function (string $fixture, string $id, ?string $isrc, array $art, bool $playable) {
    fakeSpotify(['api.spotify.com/v1/tracks/*' => Http::response(SpotifyFake::fixture($fixture))]);

    $track = $this->provider->getTrack($id);

    expect($track->isrc)->toBe($isrc)
        ->and($track->coverArtUrls)->toBe($art)
        ->and($track->playableInMarket)->toBe($playable);
})->with([
    'full' => ['track', 'track-1', 'ISRC0000001', ['https://i.scdn.co/image/one-640', 'https://i.scdn.co/image/one-300'], true],
    'no isrc or art' => ['track-no-isrc', 'track-3', null, [], true],
    'unplayable' => ['track-unplayable', 'track-4', null, [], false],
]);

it('treats a missing is_playable flag as playable', function () {
    $body = SpotifyFake::fixture('track');
    unset($body['is_playable']);
    fakeSpotify(['api.spotify.com/v1/tracks/*' => Http::response($body)]);

    expect($this->provider->getTrack('track-1')->playableInMarket)->toBeTrue();
});

it('returns null for an unknown track', function () {
    fakeSpotify(['api.spotify.com/v1/tracks/*' => Http::response(SpotifyFake::fixture('error-not-found'), 404)]);

    expect($this->provider->getTrack('nope'))->toBeNull();
});

it('caches the access token across calls', function () {
    fakeSpotify(['api.spotify.com/v1/*' => Http::response(SpotifyFake::fixture('track'))]);

    $this->provider->getTrack('a');
    $this->provider->getTrack('b');

    expect(tokenRequests())->toBe(1);
});

it('refreshes the token once on 401 and retries', function () {
    fakeSpotify(['api.spotify.com/v1/tracks/*' => Http::sequence()
        ->push(SpotifyFake::fixture('error-unauthorized'), 401)
        ->push(SpotifyFake::fixture('track'))]);

    expect($this->provider->getTrack('track-1'))->not->toBeNull()
        ->and(tokenRequests())->toBe(2);
});

it('gives up when still unauthorised after a refresh', function () {
    fakeSpotify(['api.spotify.com/v1/tracks/*' => Http::response(SpotifyFake::fixture('error-unauthorized'), 401)]);

    $this->provider->getTrack('track-1');
})->throws(ProviderUnavailableException::class);

it('reports unavailable without credentials and makes no requests', function (?string $id, ?string $secret) {
    config(['services.spotify.client_id' => $id, 'services.spotify.client_secret' => $secret]);
    Http::fake();

    expect(fn () => $this->provider->search('x', 1, 0))->toThrow(ProviderUnavailableException::class);
    Http::assertNothingSent();
})->with([
    'no id' => [null, 'secret'],
    'no secret' => ['id', null],
    'empty' => ['', ''],
]);

it('reports unavailable when the token endpoint rejects the credentials', function () {
    Http::fake(['accounts.spotify.com/*' => Http::response(['error' => 'invalid_client'], 400)]);

    $this->provider->search('x', 1, 0);
})->throws(ProviderUnavailableException::class);

it('maps 429 to a temporary failure and fails fast during back-off', function () {
    fakeSpotify(['api.spotify.com/v1/*' => Http::response(SpotifyFake::fixture('error-rate-limited'), 429, ['Retry-After' => '12'])]);

    try {
        $this->provider->search('x', 1, 0);
        $this->fail('Expected a temporary failure.');
    } catch (ProviderTemporaryFailure $e) {
        expect($e->retryAfterSeconds)->toBe(12);
    }

    $apiCalls = fn () => Http::recorded(fn (Request $r): bool => str_contains($r->url(), 'api.spotify.com'))->count();

    expect(fn () => $this->provider->getTrack('t'))->toThrow(ProviderTemporaryFailure::class)
        ->and($apiCalls())->toBe(1);
});

it('resumes after the back-off period passes', function () {
    $this->travelTo(now());
    fakeSpotify(['api.spotify.com/v1/tracks/*' => Http::sequence()
        ->push('', 429, ['Retry-After' => '10'])
        ->push(SpotifyFake::fixture('track'))]);

    expect(fn () => $this->provider->getTrack('t'))->toThrow(ProviderTemporaryFailure::class);

    $this->travel(11)->seconds();
    Cache::flush();
    Cache::put('music.spotify.access-token', 'token-abc', 60);

    expect($this->provider->getTrack('t'))->not->toBeNull();
});

it('defaults the retry-after when the header is missing', function () {
    fakeSpotify(['api.spotify.com/v1/*' => Http::response('', 429)]);

    try {
        $this->provider->search('x', 1, 0);
    } catch (ProviderTemporaryFailure $e) {
        expect($e->retryAfterSeconds)->toBe(30);
    }
});

it('maps 429 from the token endpoint to a temporary failure', function () {
    Http::fake(['accounts.spotify.com/*' => Http::response('', 429, ['Retry-After' => '5'])]);

    try {
        $this->provider->search('x', 1, 0);
        $this->fail('Expected a temporary failure.');
    } catch (ProviderTemporaryFailure $e) {
        expect($e->retryAfterSeconds)->toBe(5);
    }
});

it('maps server errors and connection failures to temporary failures', function (Closure $api) {
    fakeSpotify(['api.spotify.com/v1/*' => $api]);

    $this->provider->search('x', 1, 0);
})->with([
    '503' => [fn () => Http::response('', 503)],
    '400' => [fn () => Http::response('', 400)],
    'connection' => [function () {
        throw new ConnectionException('boom');
    }],
])->throws(ProviderTemporaryFailure::class);

it('maps a token endpoint outage to a temporary failure', function () {
    Http::fake(['accounts.spotify.com/*' => Http::response('', 502)]);

    $this->provider->search('x', 1, 0);
})->throws(ProviderTemporaryFailure::class);

it('maps an empty search response to an empty page', function () {
    fakeSpotify(['api.spotify.com/v1/search*' => Http::response(['tracks' => ['items' => [], 'total' => 0, 'limit' => 5, 'offset' => 0]])]);

    $page = $this->provider->search('zzz', 5, 0);

    expect($page->items)->toBe([])->and($page->total)->toBe(0);
});
