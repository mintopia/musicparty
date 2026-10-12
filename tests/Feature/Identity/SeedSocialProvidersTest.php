<?php

use App\Domain\Admin\Models\ProviderSetting;
use App\Domain\Identity\Models\SocialProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.discord' => ['client_id' => 'd-id', 'client_secret' => 'd-secret'],
        'services.twitch' => ['client_id' => 't-id', 'client_secret' => 't-secret'],
        'services.steam' => ['client_secret' => 'steam-key'],
        'services.spotify' => ['client_id' => null, 'client_secret' => null],
        'services.spotifysearch' => ['client_id' => 'ss-id', 'client_secret' => 'ss-secret'],
        'services.google' => ['client_id' => 'g-id', 'client_secret' => 'g-secret'],
        'services.facebook' => ['client_id' => null, 'client_secret' => null],
    ]);
});

it('seeds the seven providers and enables the configured ones', function () {
    $this->artisan('providers:seed')->assertSuccessful();

    expect(SocialProvider::query()->pluck('code')->sort()->values()->all())->toBe(['discord', 'facebook', 'google', 'spotify', 'spotifysearch', 'steam', 'twitch'])
        ->and(SocialProvider::query()->where('code', 'discord')->first()->isAvailableForLogin())->toBeTrue()
        ->and(SocialProvider::query()->where('code', 'steam')->first()->isAvailableForLogin())->toBeTrue()
        ->and(SocialProvider::query()->where('code', 'google')->firstOrFail()->isAvailableForLogin())->toBeTrue()
        ->and(SocialProvider::query()->where('code', 'spotify')->first()->enabled)->toBeFalse()
        ->and(SocialProvider::query()->where('code', 'spotifysearch')->firstOrFail()->only(['enabled', 'auth_enabled', 'supports_auth']))->toBe(['enabled' => true, 'auth_enabled' => false, 'supports_auth' => 0])
        ->and(SocialProvider::query()->where('code', 'facebook')->firstOrFail()->enabled)->toBeFalse();
});

it('is idempotent and never overwrites stored values', function () {
    $this->artisan('providers:seed')->assertSuccessful();
    $counts = [SocialProvider::query()->count(), ProviderSetting::query()->count()];

    $discord = SocialProvider::query()->where('code', 'discord')->firstOrFail();
    $discord->forceFill(['enabled' => false])->save();
    $secret = $discord->settings()->whereCode('client_secret')->firstOrFail();
    $secret->value = 'admin-changed';
    $secret->save();

    config(['services.discord.client_secret' => 'different-env']);
    $this->artisan('providers:seed')->assertSuccessful();

    expect([SocialProvider::query()->count(), ProviderSetting::query()->count()])->toBe($counts)
        ->and($discord->fresh()->enabled)->toBeFalse()
        ->and($discord->getSetting('client_secret'))->toBe('admin-changed');
});

it('stores secrets encrypted at rest', function () {
    $this->artisan('providers:seed')->assertSuccessful();

    $raw = DB::table('provider_settings')->where('code', 'client_secret')->pluck('value')->implode('|');
    $discord = SocialProvider::query()->where('code', 'discord')->firstOrFail();

    expect($raw)->not->toContain('d-secret')->not->toContain('steam-key')
        ->and($discord->getSetting('client_secret'))->toBe('d-secret')
        ->and($discord->getSetting('client_id'))->toBe('d-id');
});

it('fills empty Spotify Search settings from env and never overwrites them', function () {
    $this->artisan('providers:seed')->assertSuccessful();
    $search = SocialProvider::query()->where('code', 'spotifysearch')->firstOrFail();

    expect($search->getSetting('client_id'))->toBe('ss-id')
        ->and($search->getSetting('client_secret'))->toBe('ss-secret');

    $id = $search->settings()->whereCode('client_id')->firstOrFail();
    $id->value = 'admin-id';
    $id->save();
    $secret = $search->settings()->whereCode('client_secret')->firstOrFail();
    $secret->value = null;
    $secret->save();

    config(['services.spotifysearch' => ['client_id' => 'env-id', 'client_secret' => 'env-secret']]);
    $this->artisan('providers:seed')->assertSuccessful();

    $search = SocialProvider::query()->where('code', 'spotifysearch')->firstOrFail();

    expect($search->getSetting('client_id'))->toBe('admin-id')
        ->and($search->getSetting('client_secret'))->toBe('env-secret');
});
