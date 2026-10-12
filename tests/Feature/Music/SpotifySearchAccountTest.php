<?php

use App\Domain\Identity\Models\LinkedAccount;
use App\Domain\Identity\Models\SocialProvider;
use App\Domain\Identity\Models\User;
use App\Domain\Music\Exceptions\ProviderTemporaryFailure;
use App\Domain\Music\Providers\SpotifyMusicProvider;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Actions\SearchPartyProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\Spotify\SpotifyFake;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    config([
        'services.spotify' => ['client_id' => 'control-id', 'client_secret' => 'control-secret', 'market' => 'GB'],
        'services.spotifysearch' => ['client_id' => 'search-id', 'client_secret' => 'search-secret'],
    ]);
    $this->host = User::factory()->create();
    $this->controlProvider = SocialProvider::factory()->create(['code' => 'spotify', 'name' => 'Spotify']);
    $this->searchProvider = SocialProvider::factory()->create(['code' => 'spotifysearch', 'name' => 'Spotify Search', 'supports_auth' => false, 'auth_enabled' => false]);
    $this->searchHeaders = [];
    SpotifyFake::catalogue();
});

/**
 * @param  array<string, mixed>  $state
 */
function searchAccount(User $host, string $code, array $state = []): LinkedAccount
{
    return LinkedAccount::factory()->for($host)->create([
        'social_provider_id' => SocialProvider::query()->where('code', $code)->value('id'),
        'access_token' => "{$code}-token",
        ...$state,
    ]);
}

/**
 * @return array<int, string>
 */
function searchBearers(): array
{
    return Http::recorded(fn (Request $request): bool => str_contains($request->url(), 'v1/search'))
        ->map(fn (array $pair): string => $pair[0]->header('Authorization')[0])
        ->values()
        ->all();
}

it('searches with the Search account token when it is linked', function () {
    searchAccount($this->host, 'spotify');
    searchAccount($this->host, 'spotifysearch');

    SpotifyFake::provider()->search('song', 5, 0, $this->host);

    expect(searchBearers())->toBe(['Bearer spotifysearch-token']);
});

it('searches with the control token when no Search account is linked', function () {
    searchAccount($this->host, 'spotify');

    SpotifyFake::provider()->search('song', 5, 0, $this->host);

    expect(searchBearers())->toBe(['Bearer spotify-token']);
});

it('falls back to the control token when the Search account needs a relink', function () {
    searchAccount($this->host, 'spotify');
    searchAccount($this->host, 'spotifysearch', ['needs_relink' => true]);

    SpotifyFake::provider()->search('song', 5, 0, $this->host);

    expect(searchBearers())->toBe(['Bearer spotify-token']);
});

it('ignores the Search account while the admin has not enabled the provider', function () {
    searchAccount($this->host, 'spotify');
    searchAccount($this->host, 'spotifysearch');
    $this->searchProvider->forceFill(['enabled' => false])->save();

    SpotifyFake::provider()->search('song', 5, 0, $this->host);

    expect(searchBearers())->toBe(['Bearer spotify-token']);
});

it('uses client credentials when the Host links nothing or a control account needing relink', function (bool $linkedControl) {
    if ($linkedControl) {
        searchAccount($this->host, 'spotify', ['needs_relink' => true]);
    }

    SpotifyFake::provider()->search('song', 5, 0, $this->host);

    $token = SpotifyFake::fixture('token')['access_token'];
    expect(searchBearers())->toBe(["Bearer {$token}"]);
})->with([[false], [true]]);

it('uses client credentials when no Host is given', function () {
    SpotifyFake::provider()->search('song', 5, 0);

    expect(searchBearers())->toBe(['Bearer '.SpotifyFake::fixture('token')['access_token']]);
});

it('marks a rejected Search account for relink and falls back to the control account', function () {
    searchAccount($this->host, 'spotify');
    $search = searchAccount($this->host, 'spotifysearch', ['access_token_expires_at' => now()->subMinute()]);
    Http::swap(new Factory);
    Http::fake([
        'accounts.spotify.com/*' => Http::response(SpotifyFake::fixture('error-invalid-grant'), 400),
        'api.spotify.com/v1/search*' => Http::response(['tracks' => ['items' => [], 'total' => 0, 'offset' => 0, 'limit' => 5]]),
    ]);

    SpotifyFake::provider()->search('song', 5, 0, $this->host);

    expect($search->fresh()?->needs_relink)->toBeTrue()
        ->and(searchBearers())->toBe(['Bearer spotify-token']);
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'accounts.spotify.com') && str_contains($request->header('Authorization')[0], base64_encode('search-id:search-secret')));
});

it('keeps a separate backoff for the Search application', function () {
    searchAccount($this->host, 'spotifysearch');
    Http::swap(new Factory);
    Http::fake(['api.spotify.com/*' => Http::response('', 429, ['Retry-After' => '9'])]);
    $provider = SpotifyFake::provider();

    expect(fn () => $provider->search('song', 5, 0, $this->host))->toThrow(ProviderTemporaryFailure::class);
    expect(fn () => $provider->search('song', 5, 0, $this->host))->toThrow(ProviderTemporaryFailure::class);

    expect(Http::recorded()->count())->toBe(1)
        ->and(Cache::get('music.spotify.backoff.'.hash('sha256', 'search-id')))->toBe(now()->getTimestamp() + 9)
        ->and(Cache::get('music.spotify.backoff.'.hash('sha256', 'control-id')))->toBeNull();
});

it('exposes the Search provider with a Host through the party search action', function () {
    $party = Party::factory()->live()->create(['user_id' => $this->host->id, 'music_provider' => 'spotify']);
    searchAccount($this->host, 'spotifysearch');
    config(['musicparty.music_providers.spotify' => ['label' => 'Spotify', 'class' => SpotifyMusicProvider::class]]);

    app(SearchPartyProvider::class)($party, 'song');

    expect(searchBearers())->toBe(['Bearer spotifysearch-token']);
});
