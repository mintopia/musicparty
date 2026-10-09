<?php

use App\Models\ProviderSetting;
use App\Models\SocialProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.discord' => ['client_id' => 'd-id', 'client_secret' => 'd-secret'],
        'services.twitch' => ['client_id' => 't-id', 'client_secret' => 't-secret'],
        'services.steam' => ['client_secret' => 'steam-key'],
        'services.spotify' => ['client_id' => null, 'client_secret' => null],
    ]);
});

it('seeds the four providers and enables the configured ones', function () {
    $this->artisan('providers:seed')->assertSuccessful();

    expect(SocialProvider::query()->pluck('code')->sort()->values()->all())->toBe(['discord', 'spotify', 'steam', 'twitch'])
        ->and(SocialProvider::query()->where('code', 'discord')->first()->isAvailableForLogin())->toBeTrue()
        ->and(SocialProvider::query()->where('code', 'steam')->first()->isAvailableForLogin())->toBeTrue()
        ->and(SocialProvider::query()->where('code', 'spotify')->first()->enabled)->toBeFalse();
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
