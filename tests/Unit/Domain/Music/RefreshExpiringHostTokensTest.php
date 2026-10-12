<?php

use App\Domain\Identity\Models\LinkedAccount;
use App\Domain\Identity\Models\SocialProvider;
use App\Domain\Music\Jobs\RefreshExpiringHostTokens;
use App\Domain\Music\Providers\Spotify\HostAccountTokens;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\Spotify\SpotifyFake;

beforeEach(function () {
    SpotifyFake::useInMemoryDatabase();
    config([
        'services.spotify' => ['client_id' => 'id', 'client_secret' => 'secret'],
        'services.spotifysearch' => ['client_id' => 'sid', 'client_secret' => 'ssecret'],
    ]);
    Cache::flush();
    Carbon::setTestNow('2026-10-09 12:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

function hostTokenProviderRow(string $code): SocialProvider
{
    return SocialProvider::factory()->create(['code' => $code, 'name' => $code]);
}

function runHostTokenRefreshJob(): void
{
    (new RefreshExpiringHostTokens)->handle(new HostAccountTokens);
}

function hostTokenRefreshRequests(): int
{
    return Http::recorded(fn (Request $r): bool => str_contains($r->url(), 'accounts.spotify.com'))->count();
}

it('refreshes control and search tokens expiring within five minutes', function () {
    SpotifyFake::hostApi();
    $control = SpotifyFake::account()->create(['social_provider_id' => hostTokenProviderRow('spotify')->id, 'access_token_expires_at' => now()->addMinutes(3)]);
    $search = SpotifyFake::account()->create(['social_provider_id' => hostTokenProviderRow('spotifysearch')->id, 'access_token_expires_at' => now()->addMinutes(3)]);

    runHostTokenRefreshJob();

    expect(hostTokenRefreshRequests())->toBe(2)
        ->and($control->refresh()->access_token)->toBe('new-access-token')
        ->and($search->refresh()->access_token)->toBe('new-access-token');
});

it('leaves fresh tokens, other providers and accounts needing relink alone', function () {
    SpotifyFake::hostApi();
    $spotify = hostTokenProviderRow('spotify');
    SpotifyFake::account()->create(['social_provider_id' => $spotify->id, 'access_token_expires_at' => now()->addMinutes(30)]);
    SpotifyFake::account()->needingRelink()->create(['social_provider_id' => $spotify->id, 'access_token_expires_at' => now()->addMinutes(3)]);
    SpotifyFake::account()->create(['social_provider_id' => hostTokenProviderRow('discord')->id, 'access_token_expires_at' => now()->addMinutes(3)]);

    runHostTokenRefreshJob();

    expect(hostTokenRefreshRequests())->toBe(0);
});

it('refreshes once when an on-demand refresh has already happened', function () {
    SpotifyFake::hostApi();
    $account = SpotifyFake::account()->create(['social_provider_id' => hostTokenProviderRow('spotify')->id, 'access_token_expires_at' => now()->addMinutes(3)]);
    $stale = LinkedAccount::query()->findOrFail($account->id);

    (new HostAccountTokens)->accessToken($account);
    (new HostAccountTokens)->accessToken($stale);
    runHostTokenRefreshJob();

    expect(hostTokenRefreshRequests())->toBe(1);
});

it('marks the account needs-relink on invalid_grant and continues', function () {
    Http::fake([
        'accounts.spotify.com/*' => Http::sequence()
            ->push(SpotifyFake::fixture('error-invalid-grant'), 400)
            ->push(SpotifyFake::fixture('token-refresh')),
    ]);
    $spotify = hostTokenProviderRow('spotify');
    $bad = SpotifyFake::account()->create(['social_provider_id' => $spotify->id, 'access_token_expires_at' => now()->addMinutes(2)]);
    $good = SpotifyFake::account()->create(['social_provider_id' => $spotify->id, 'access_token_expires_at' => now()->addMinutes(3)]);

    runHostTokenRefreshJob();

    expect($bad->refresh()->needs_relink)->toBeTrue()
        ->and($good->refresh()->access_token)->toBe('new-access-token');
});

it('is scheduled every minute on one server', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($e): bool => str_contains($e->description ?? '', RefreshExpiringHostTokens::class));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->onOneServer)->toBeTrue();
});
