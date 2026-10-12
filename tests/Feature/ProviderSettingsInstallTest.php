<?php

use App\Models\ProviderSetting;
use App\Services\SocialProviders\DiscordProvider;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', ':memory:');
    $this->artisan('migrate:fresh', ['--path' => [
        'database/migrations/2023_12_04_001057_create_social_providers_table.php',
        'database/migrations/2024_01_09_182839_create_provider_settings_table.php',
    ]]);
});

it('stores encrypted provider settings so the value can be read back', function () {
    $provider = (new DiscordProvider(null, 'https://example.test/callback'))->install();

    $secret = ProviderSetting::query()
        ->where('code', 'client_secret')
        ->firstOrFail();

    expect($secret->encrypted)->toBeTruthy()
        ->and(Crypt::decrypt($secret->getRawOriginal('value')))->toBe(serialize(null))
        ->and($secret->value)->toBeNull();

    $secret->value = 'shh';
    $secret->save();

    expect(ProviderSetting::query()->findOrFail($secret->id)->value)->toBe('shh')
        ->and($provider->getSetting('client_id'))->toBeNull();
});

it('does not overwrite an existing value when reinstalling settings', function () {
    $discord = new DiscordProvider(null, 'https://example.test/callback');
    $discord->install();

    $secret = ProviderSetting::query()->where('code', 'client_secret')->firstOrFail();
    $secret->value = 'keep-me';
    $secret->save();

    $discord->installSettings();

    expect(ProviderSetting::query()->findOrFail($secret->id)->value)->toBe('keep-me');
});
