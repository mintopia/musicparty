<?php

use App\Domain\Identity\Models\LinkedAccount;
use App\Domain\Identity\Models\SocialProvider;
use App\Domain\Identity\Models\User;
use App\Domain\Party\Models\Party;
use App\Domain\Playback\Contracts\PlaybackClient;
use App\Domain\Playback\Spotify\SpotifyPlaybackClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\Fixtures\Spotify\SpotifyFake;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    config(['services.spotify' => ['client_id' => 'id', 'client_secret' => 'secret']]);
    app()->bind(PlaybackClient::class, SpotifyPlaybackClient::class);
    $this->owner = User::factory()->create();
    $this->party = Party::factory()->live()->create(['code' => 'POLL', 'player_kind' => 'polling', 'music_provider' => 'spotify', 'user_id' => $this->owner->id]);
    LinkedAccount::factory()
        ->for($this->owner)
        ->create(['social_provider_id' => SocialProvider::factory()->create(['code' => 'spotify'])->id, 'access_token' => 'host-token']);
    Sanctum::actingAs($this->owner);
});

/**
 * @param  array<string, int>  $payload
 * @return TestResponse<Response>
 */
function pollingControl(string $control, array $payload = []): TestResponse
{
    return test()->postJson("/api/v1/parties/POLL/playback/{$control}", $payload);
}

it('drives the Host Spotify device for each control', function (string $control, array $payload, string $method, string $path) {
    SpotifyFake::hostApi(['api.spotify.com/v1/me/player/*' => Http::response('', 204)]);

    pollingControl($control, $payload)->assertOk()->assertJsonPath('data.control', $control);

    Http::assertSent(fn (Request $r): bool => $r->method() === $method
        && str_starts_with($r->url(), "https://api.spotify.com/v1/me/player/{$path}")
        && $r->hasHeader('Authorization', 'Bearer host-token'));
})->with([
    'play' => ['play', [], 'PUT', 'play'],
    'pause' => ['pause', [], 'PUT', 'pause'],
    'skip' => ['skip', [], 'POST', 'next'],
    'seek' => ['seek', ['position_ms' => 42000], 'PUT', 'seek?position_ms=42000'],
    'volume' => ['volume', ['level' => 65], 'PUT', 'volume?volume_percent=65'],
    'volume zero' => ['volume', ['level' => 0], 'PUT', 'volume?volume_percent=0'],
]);

it('rejects an out-of-range volume without calling Spotify', function () {
    SpotifyFake::hostApi();

    pollingControl('volume', ['level' => 101])->assertUnprocessable()->assertJsonValidationErrors('level');

    Http::assertNothingSent();
});

it('returns a clear conflict when Spotify has no active device', function () {
    SpotifyFake::hostApi(['api.spotify.com/v1/me/player/*' => Http::response(['error' => ['status' => 404, 'reason' => 'NO_ACTIVE_DEVICE']], 404)]);

    pollingControl('play')->assertStatus(409)->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'no active device'));
});

it('returns a clear conflict when Spotify restricts the command', function () {
    SpotifyFake::hostApi(['api.spotify.com/v1/me/player/*' => Http::response(['error' => ['status' => 403, 'reason' => 'PREMIUM_REQUIRED', 'message' => 'Player command failed: Premium required']], 403)]);

    pollingControl('skip')->assertStatus(409)->assertJsonPath('message', 'Spotify refused the command: Player command failed: Premium required');
});

it('returns 429 while Spotify is rate limiting', function () {
    SpotifyFake::hostApi(['api.spotify.com/v1/me/player/*' => Http::response('', 429, ['Retry-After' => '7'])]);

    pollingControl('pause')->assertStatus(429)->assertJsonPath('message', fn (string $m): bool => str_contains($m, '7 seconds'));

    pollingControl('pause')->assertStatus(429);
    Http::assertSentCount(1);
});

it('reports a disconnected player when Spotify rejects the Host token', function () {
    SpotifyFake::hostApi(['api.spotify.com/v1/me/player/*' => Http::response(['error' => ['status' => 403, 'message' => 'Insufficient client scope']], 403)]);

    pollingControl('play')->assertStatus(409)->assertJsonPath('message', 'The Player is disconnected, so playback cannot be controlled.');
});

it('reports a disconnected player when the Host has no linked account', function () {
    LinkedAccount::query()->delete();
    SpotifyFake::hostApi();

    pollingControl('play')->assertStatus(409);

    Http::assertNothingSent();
});

it('still refuses every control on the Browser Player', function (string $control, array $payload) {
    Party::factory()->live()->create(['code' => 'BRWS', 'player_kind' => 'browser', 'user_id' => $this->owner->id]);

    test()->postJson("/api/v1/parties/BRWS/playback/{$control}", $payload)->assertUnprocessable()
        ->assertJsonPath('message', "This Player does not support {$control}.");
})->with([
    'play' => ['play', []],
    'seek' => ['seek', ['position_ms' => 1000]],
    'volume' => ['volume', ['level' => 10]],
]);
