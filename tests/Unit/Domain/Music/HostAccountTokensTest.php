<?php

use App\Domain\Music\Accounts\HostAccountTokens;
use App\Domain\Music\Exceptions\HostAccountNeedsRelink;
use App\Domain\Music\Exceptions\ProviderTemporaryFailure;
use App\Domain\Music\Exceptions\ProviderUnavailableException;
use App\Models\LinkedAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\Spotify\SpotifyFake;

beforeEach(function () {
    SpotifyFake::useInMemoryDatabase();
    config(['services.spotify' => ['client_id' => 'id', 'client_secret' => 'secret']]);
    Cache::flush();
    Carbon::setTestNow('2026-10-09 12:00:00');
    $this->tokens = new HostAccountTokens;
});

afterEach(function () {
    Carbon::setTestNow();
});

function refreshRequests(): int
{
    return Http::recorded(fn (Request $r): bool => str_contains($r->url(), 'accounts.spotify.com'))->count();
}

it('returns the stored token without refreshing when it is fresh', function () {
    Http::preventStrayRequests();
    $account = SpotifyFake::account()->create(['access_token' => 'still-good', 'access_token_expires_at' => now()->addMinutes(10)]);

    expect($this->tokens->accessToken($account))->toBe('still-good');
    Http::assertNothingSent();
});

it('refreshes a token that is inside the five minute window', function (string $offset) {
    SpotifyFake::hostApi();
    $account = SpotifyFake::account()->create([
        'access_token' => 'old', 'refresh_token' => 'refresh-1', 'access_token_expires_at' => now()->modify($offset),
    ]);

    expect($this->tokens->accessToken($account))->toBe('new-access-token');

    $account->refresh();
    expect($account->access_token)->toBe('new-access-token')
        ->and($account->refresh_token)->toBe('refresh-1')
        ->and($account->access_token_expires_at->getTimestamp())->toBe(now()->addHour()->getTimestamp());

    Http::assertSent(fn (Request $r): bool => $r->url() === 'https://accounts.spotify.com/api/token'
        && $r['grant_type'] === 'refresh_token'
        && $r['refresh_token'] === 'refresh-1'
        && $r->hasHeader('Authorization', 'Basic '.base64_encode('id:secret')));
})->with(['inside window' => '+200 seconds', 'expired' => '-1 minute']);

it('refreshes when the expiry is unknown', function () {
    SpotifyFake::hostApi();
    $account = SpotifyFake::account()->create(['access_token_expires_at' => null]);

    expect($this->tokens->accessToken($account))->toBe('new-access-token');
});

it('stores a rotated refresh token', function () {
    SpotifyFake::hostApi(['accounts.spotify.com/*' => Http::response(SpotifyFake::fixture('token-refresh-rotated'))]);
    $account = SpotifyFake::account()->expired()->create();

    $this->tokens->accessToken($account);

    expect($account->refresh()->refresh_token)->toBe('rotated-refresh-token');
});

it('is single-flight: a second caller holding a stale model sees the refreshed token', function () {
    SpotifyFake::hostApi();
    $account = SpotifyFake::account()->expired()->create();
    $stale = LinkedAccount::query()->findOrFail($account->getKey());

    $first = $this->tokens->accessToken($account);
    $second = $this->tokens->accessToken($stale);

    expect($first)->toBe('new-access-token')
        ->and($second)->toBe('new-access-token')
        ->and(refreshRequests())->toBe(1);
});

it('marks the account for relink when Spotify rejects the refresh', function (int $status) {
    SpotifyFake::hostApi(['accounts.spotify.com/*' => Http::response(SpotifyFake::fixture('error-invalid-grant'), $status)]);
    $account = SpotifyFake::account()->expired()->create(['refresh_token' => 'secret-refresh']);

    try {
        $this->tokens->accessToken($account);
        $this->fail('Expected HostAccountNeedsRelink');
    } catch (HostAccountNeedsRelink $e) {
        expect($e->getMessage())->not->toContain('secret-refresh')->not->toContain($account->access_token);
    }

    expect($account->refresh()->needs_relink)->toBeTrue();
})->with([400, 401]);

it('treats non-grant rejections as provider unavailable and leaves the account linked', function (int $status, array $body) {
    SpotifyFake::hostApi(['accounts.spotify.com/*' => Http::response($body, $status)]);
    $account = SpotifyFake::account()->expired()->create();

    expect(fn () => $this->tokens->accessToken($account))->toThrow(ProviderUnavailableException::class)
        ->and($account->refresh()->needs_relink)->toBeFalse();
})->with([
    'invalid_client' => [401, ['error' => 'invalid_client']],
    'invalid_request' => [400, ['error' => 'invalid_request']],
    'unparseable body' => [400, []],
]);

it('recovers once a bad client secret is fixed', function () {
    SpotifyFake::hostApi(['accounts.spotify.com/*' => Http::sequence()
        ->push(['error' => 'invalid_client'], 401)
        ->push(SpotifyFake::fixture('token-refresh'))]);
    $account = SpotifyFake::account()->expired()->create();

    expect(fn () => $this->tokens->accessToken($account))->toThrow(ProviderUnavailableException::class)
        ->and($this->tokens->accessToken($account))->toBe('new-access-token');
});

it('does not call Spotify once relink is required', function () {
    Http::preventStrayRequests();
    $account = SpotifyFake::account()->expired()->needingRelink()->create();

    expect(fn () => $this->tokens->accessToken($account))->toThrow(HostAccountNeedsRelink::class);
    Http::assertNothingSent();
});

it('treats server errors, rate limits and network failures as temporary', function (Closure $response) {
    SpotifyFake::hostApi(['accounts.spotify.com/*' => $response]);
    $account = SpotifyFake::account()->expired()->create();

    expect(fn () => $this->tokens->accessToken($account))->toThrow(ProviderTemporaryFailure::class)
        ->and($account->refresh()->needs_relink)->toBeFalse();
})->with([
    '503' => [fn () => Http::response('', 503)],
    '429' => [fn () => Http::response('', 429, ['Retry-After' => '7'])],
    'network' => [fn () => fn () => throw new ConnectionException('down')],
]);

it('encrypts tokens at rest and hides them from serialization', function () {
    $account = SpotifyFake::account()->create(['access_token' => 'plain-access', 'refresh_token' => 'plain-refresh']);

    $raw = (array) DB::table('linked_accounts')->where('id', $account->getKey())->first();

    expect($raw['access_token'])->not->toBe('plain-access')->not->toContain('plain-access')
        ->and($raw['refresh_token'])->not->toContain('plain-refresh')
        ->and($account->fresh()->access_token)->toBe('plain-access')
        ->and($account->toArray())->not->toHaveKeys(['access_token', 'refresh_token'])
        ->and($account->toJson())->not->toContain('plain-access')->not->toContain('plain-refresh');
});
