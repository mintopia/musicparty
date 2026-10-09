<?php

use App\Domain\Music\Accounts\HostAccountTokens;
use App\Domain\Music\Capability;
use App\Domain\Music\Exceptions\ProviderTemporaryFailure;
use App\Domain\Music\Exceptions\ProviderUnavailableException;
use App\Domain\Music\Providers\SpotifyMusicProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\Spotify\SpotifyFake;

beforeEach(function () {
    SpotifyFake::useInMemoryDatabase();
    config(['services.spotify' => ['client_id' => 'id', 'client_secret' => 'secret', 'market' => 'GB']]);
    Cache::flush();
    $this->account = SpotifyFake::account()->create(['access_token' => 'host-token']);
    $this->host = (string) $this->account->getKey();
    $this->provider = new SpotifyMusicProvider(new HostAccountTokens);
});

it('supports playlist write', function () {
    expect($this->provider->supports(Capability::PlaylistWrite))->toBeTrue();
});

it('lists playlists across pages using the host token', function () {
    SpotifyFake::hostApi();

    $playlists = $this->provider->playlists($this->host);

    expect($playlists)->toHaveCount(3)
        ->and($playlists[0]->id)->toBe('pl-1')
        ->and($playlists[0]->name)->toBe('Party Mix')
        ->and($playlists[0]->trackCount)->toBe(3)
        ->and($playlists[2]->trackCount)->toBe(12);

    Http::assertSent(fn (Request $r): bool => str_contains($r->url(), '/me/playlists') && $r->hasHeader('Authorization', 'Bearer host-token'));
    Http::assertSentCount(3 - 1);
});

it('maps playlist tracks skipping null, local and episode items', function () {
    SpotifyFake::hostApi();

    $tracks = $this->provider->playlistTracks('pl-1', $this->host);

    expect(array_map(fn ($t) => $t->providerTrackId, $tracks))->toBe(['track-1', 'track-2'])
        ->and($tracks[0]->isrc)->toBe('ISRC0000001');
});

it('appends in chunks of 100 as spotify uris', function () {
    SpotifyFake::hostApi(['api.spotify.com/v1/playlists/*/tracks*' => Http::response(['snapshot_id' => 'x'], 201)]);
    $ids = array_map(fn (int $i): string => "t{$i}", range(1, 250));

    $this->provider->appendToPlaylist('pl-1', $ids, $this->host);

    $sizes = Http::recorded()->map(fn (array $pair): int => count($pair[0]['uris']))->all();
    expect($sizes)->toBe([100, 100, 50]);
    Http::assertSent(fn (Request $r): bool => $r->method() === 'POST' && $r->url() === 'https://api.spotify.com/v1/playlists/pl-1/tracks' && $r['uris'][0] === 'spotify:track:t1');
});

it('appends nothing and sends no request for an empty list', function () {
    SpotifyFake::hostApi();

    $this->provider->appendToPlaylist('pl-1', [], $this->host);

    Http::assertNothingSent();
});

it('maps append server failures to a temporary failure', function () {
    SpotifyFake::hostApi(['api.spotify.com/v1/playlists/*/tracks*' => Http::response('', 502)]);

    expect(fn () => $this->provider->appendToPlaylist('pl-1', ['t1'], $this->host))->toThrow(ProviderTemporaryFailure::class);
});

it('maps 401 and 403 to provider unavailable', function (int $status) {
    SpotifyFake::hostApi(['api.spotify.com/v1/playlists/*/tracks*' => Http::response('', $status)]);

    expect(fn () => $this->provider->appendToPlaylist('pl-1', ['t1'], $this->host))->toThrow(ProviderUnavailableException::class);
})->with([401, 403]);

it('backs off after a 429 and short-circuits further requests', function () {
    SpotifyFake::hostApi(['api.spotify.com/v1/playlists/*/tracks*' => Http::response('', 429, ['Retry-After' => '12'])]);

    try {
        $this->provider->playlistTracks('pl-1', $this->host);
        $this->fail('Expected backoff');
    } catch (ProviderTemporaryFailure $e) {
        expect($e->retryAfterSeconds)->toBe(12);
    }

    expect(fn () => $this->provider->appendToPlaylist('pl-1', ['t1'], $this->host))->toThrow(ProviderTemporaryFailure::class);
    Http::assertSentCount(1);
});

it('rejects unknown or malformed host account ids', function (string $id) {
    SpotifyFake::hostApi();

    expect(fn () => $this->provider->playlists($id))->toThrow(ProviderUnavailableException::class);
    Http::assertNothingSent();
})->with(['9999', 'abc', '']);

it('refreshes the host token and retries once when Spotify rejects it', function () {
    SpotifyFake::hostApi(['api.spotify.com/v1/playlists/*/tracks*' => Http::sequence()
        ->push('', 401)
        ->push(['snapshot_id' => 'abc'], 201)]);

    $this->provider->appendToPlaylist('pl-1', ['t1'], $this->host);

    Http::assertSentCount(3);
    Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'accounts.spotify.com'));
});
