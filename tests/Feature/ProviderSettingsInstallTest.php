<?php

use App\Models\ProviderSetting;
use App\Services\SocialProviders\DiscordProvider;
use Illuminate\Support\Facades\Crypt;

it('stores encrypted provider settings so the value can be read back', function () {
    $provider = new DiscordProvider(null, 'https://example.test/callback')->install();

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
