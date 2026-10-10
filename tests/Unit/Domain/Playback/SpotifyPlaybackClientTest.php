<?php

use App\Domain\Music\Exceptions\HostAccountNeedsRelink;
use App\Domain\Music\Exceptions\ProviderTemporaryFailure;
use App\Domain\Music\Exceptions\ProviderUnavailableException;
use App\Domain\Music\Providers\Spotify\HostAccountTokens;
use App\Domain\Music\Providers\Spotify\SpotifyApi;
use App\Domain\Playback\PlaybackStatus;
use App\Domain\Playback\Spotify\SpotifyPlaybackClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\Spotify\SpotifyFake;

beforeEach(function () {
    SpotifyFake::useInMemoryDatabase();
    config(['services.spotify' => ['client_id' => 'id', 'client_secret' => 'secret', 'market' => 'GB']]);
    Cache::flush();
    $this->account = SpotifyFake::account()->createOne(['access_token' => 'host-token']);
    $this->host = (string) $this->account->getKey();
    $this->client = new SpotifyPlaybackClient(new SpotifyApi(new HostAccountTokens));
});

it('maps a playing device to a playback state with position and duration', function () {
    SpotifyFake::hostApi(['api.spotify.com/v1/me/player*' => Http::response(SpotifyFake::fixture('player-playing'))]);

    $state = $this->client->currentPlayback($this->host);

    expect($state->status)->toBe(PlaybackStatus::Playing)
        ->and($state->currentTrack->providerId)->toBe('spotify')
        ->and($state->currentTrack->providerTrackId)->toBe('track-1')
        ->and($state->positionMs)->toBe(61000)
        ->and($state->durationMs)->toBe(180000);
    Http::assertSent(fn (Request $r): bool => str_contains($r->url(), '/v1/me/player') && $r->hasHeader('Authorization', 'Bearer host-token'));
});

it('maps a paused device to paused', function () {
    SpotifyFake::hostApi(['api.spotify.com/v1/me/player*' => Http::response(SpotifyFake::fixture('player-paused'))]);

    expect($this->client->currentPlayback($this->host)->status)->toBe(PlaybackStatus::Paused);
});

it('reports the original id when spotify relinked the track', function () {
    $body = SpotifyFake::fixture('player-playing');
    $body['item']['id'] = 'relinked-1';
    $body['item']['linked_from'] = ['id' => 'track-1'];
    SpotifyFake::hostApi(['api.spotify.com/v1/me/player*' => Http::response($body)]);

    expect($this->client->currentPlayback($this->host)->currentTrack->providerTrackId)->toBe('track-1');
});

it('treats no active device and non-track items as idle', function (mixed $response) {
    SpotifyFake::hostApi(['api.spotify.com/v1/me/player*' => $response]);

    $state = $this->client->currentPlayback($this->host);

    expect($state->status)->toBe(PlaybackStatus::Stopped)->and($state->currentTrack)->toBeNull();
})->with([
    'no content' => fn () => Http::response('', 204),
    'accepted without device' => fn () => Http::response('', 202),
    'episode' => fn () => Http::response(SpotifyFake::fixture('player-episode')),
    'no item' => fn () => Http::response(['is_playing' => true, 'item' => null]),
]);

it('surfaces rate limiting and server errors as temporary failures', function () {
    SpotifyFake::hostApi(['api.spotify.com/v1/me/player*' => Http::sequence()
        ->push('', 503)
        ->push(SpotifyFake::fixture('error-rate-limited'), 429, ['Retry-After' => '17'])]);

    expect(fn () => $this->client->currentPlayback($this->host))->toThrow(ProviderTemporaryFailure::class);

    try {
        $this->client->currentPlayback($this->host);
        $this->fail('Expected a temporary failure.');
    } catch (ProviderTemporaryFailure $failure) {
        expect($failure->retryAfterSeconds)->toBe(17);
    }
});

it('reports unavailable on a forbidden playback request', function () {
    SpotifyFake::hostApi(['api.spotify.com/v1/me/player*' => Http::response(SpotifyFake::fixture('error-unauthorized'), 403)]);

    $this->client->currentPlayback($this->host);
})->throws(ProviderUnavailableException::class);

it('requires a relink when the account is flagged', function () {
    SpotifyFake::hostApi();
    $this->account->forceFill(['needs_relink' => true])->save();

    $this->client->currentPlayback($this->host);
})->throws(HostAccountNeedsRelink::class);

it('adds a track to the host queue as a spotify uri', function () {
    SpotifyFake::hostApi(['api.spotify.com/v1/me/player/queue*' => Http::response('', 204)]);

    $this->client->queueTrack('track-9', $this->host);

    Http::assertSent(fn (Request $r): bool => $r->method() === 'POST'
        && str_starts_with($r->url(), 'https://api.spotify.com/v1/me/player/queue?uri=spotify%3Atrack%3Atrack-9')
        && $r->hasHeader('Authorization', 'Bearer host-token'));
});

it('treats a missing active device as a temporary failure when queueing', function () {
    SpotifyFake::hostApi(['api.spotify.com/v1/me/player/queue*' => Http::response(['error' => ['status' => 404, 'reason' => 'NO_ACTIVE_DEVICE']], 404)]);

    $this->client->queueTrack('track-9', $this->host);
})->throws(ProviderTemporaryFailure::class);
