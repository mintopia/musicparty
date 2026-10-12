<?php

use App\Domain\Admin\Actions\UpdateSocialProvider;
use App\Domain\Identity\Models\LinkedAccount;
use App\Domain\Identity\Models\SocialProvider;
use App\Domain\Identity\Models\User;
use App\Domain\Music\Exceptions\ProviderTemporaryFailure;
use App\Domain\Music\Exceptions\ProviderUnavailableException;
use App\Domain\Music\Providers\Spotify\HostAccountTokens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\Spotify\SpotifyFake;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    Http::preventStrayRequests();
    config(['services.spotify' => ['client_id' => null, 'client_secret' => null, 'market' => 'GB']]);
    config(['services.spotifysearch' => ['client_id' => null, 'client_secret' => null]]);
});

function expiredHostAccount(string $providerCode): LinkedAccount
{
    $provider = SocialProvider::query()->where('code', $providerCode)->firstOrFail();

    return LinkedAccount::factory()->create([
        'user_id' => User::factory()->create()->getKey(),
        'social_provider_id' => $provider->getKey(),
        'refresh_token' => 'refresh-1',
        'access_token' => 'old',
        'access_token_expires_at' => now()->subMinute(),
    ]);
}

it('sends stored client credentials for the search token with env empty', function () {
    configureSpotifyCredentials('spotify', 'stored-id', 'stored-secret');
    Http::fake([
        'accounts.spotify.com/*' => Http::response(SpotifyFake::fixture('token')),
        'api.spotify.com/v1/search*' => Http::response(SpotifyFake::fixture('search')),
    ]);

    SpotifyFake::provider()->search('song', 2, 0);

    Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'accounts.spotify.com/api/token')
        && $r->hasHeader('Authorization', 'Basic '.base64_encode('stored-id:stored-secret')));
});

it('refreshes a control token with the stored spotify credentials', function (string $code) {
    configureSpotifyCredentials('spotify', 'control-id', 'control-secret');
    configureSpotifyCredentials('spotifysearch', 'search-id', 'search-secret');
    SpotifyFake::hostApi();

    (new HostAccountTokens)->accessToken(expiredHostAccount($code));

    $expected = $code === 'spotify' ? 'control-id:control-secret' : 'search-id:search-secret';
    Http::assertSent(fn (Request $r): bool => $r->hasHeader('Authorization', 'Basic '.base64_encode($expected)));
})->with(['control' => 'spotify', 'search account' => 'spotifysearch']);

it('throws unavailable and sends nothing when only env credentials exist', function () {
    configureSpotifyCredentials('spotify', 'x', 'y')->settings->each(fn ($s) => $s->forceFill(['value' => null])->save());
    app()->forgetScopedInstances();
    config(['services.spotify.client_id' => 'env-id', 'services.spotify.client_secret' => 'env-secret']);
    Http::fake();

    expect(fn () => SpotifyFake::provider()->search('song', 2, 0))->toThrow(ProviderUnavailableException::class)
        ->and(fn () => (new HostAccountTokens)->accessToken(expiredHostAccount('spotify')))->toThrow(ProviderUnavailableException::class);

    Http::assertNothingSent();
});

it('uses a secret the admin just updated on the next request', function () {
    configureSpotifyCredentials('spotify', 'id', 'old-secret');
    Http::fake([
        'accounts.spotify.com/*' => Http::response(SpotifyFake::fixture('token')),
        'api.spotify.com/v1/search*' => Http::response(SpotifyFake::fixture('search')),
    ]);
    SpotifyFake::provider()->search('song', 2, 0);
    Cache::flush();

    app(UpdateSocialProvider::class)->handle(User::factory()->create(), 'spotify', null, ['client_secret' => 'new-secret']);
    app()->forgetScopedInstances();
    SpotifyFake::provider()->search('song', 2, 0);

    Http::assertSent(fn (Request $r): bool => $r->hasHeader('Authorization', 'Basic '.base64_encode('id:new-secret')));
});

it('keys the rate limit backoff on the current client id', function () {
    Http::fake([
        'accounts.spotify.com/*' => Http::response(SpotifyFake::fixture('token')),
        'api.spotify.com/v1/search*' => Http::sequence()
            ->push([], 429, ['Retry-After' => '9'])
            ->push(SpotifyFake::fixture('search')),
    ]);
    configureSpotifyCredentials('spotify', 'first-id', 'secret');

    expect(fn () => SpotifyFake::provider()->search('song', 2, 0))->toThrow(ProviderTemporaryFailure::class)
        ->and(Cache::get('music.spotify.backoff.'.hash('sha256', 'first-id')))->not->toBeNull();

    configureSpotifyCredentials('spotify', 'second-id', 'secret');

    expect(SpotifyFake::provider()->search('song', 2, 0)->total)->toBe(2)
        ->and(Cache::get('music.spotify.backoff.'.hash('sha256', 'second-id')))->toBeNull();
});
