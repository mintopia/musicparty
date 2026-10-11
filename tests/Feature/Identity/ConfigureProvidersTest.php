<?php

use App\Domain\Identity\Models\SocialProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function discord(): SocialProvider
{
    return SocialProvider::query()->where('code', 'discord')->firstOrFail();
}

it('configures settings and enables the provider', function () {
    $this->artisan('providers:configure', ['provider' => 'discord'])
        ->expectsQuestion('Client ID (required)', 'the-id')
        ->expectsQuestion('Client Secret (required)', 'the-secret')
        ->expectsConfirmation('Enable provider?', 'yes')
        ->expectsConfirmation('Allow login with it?', 'yes')
        ->assertSuccessful();

    expect(discord()->isAvailableForLogin())->toBeTrue()
        ->and(discord()->settings()->whereCode('client_id')->firstOrFail()->value)->toBe('the-id');
});

it('lets the operator pick the provider', function () {
    $this->artisan('providers:configure')
        ->expectsChoice('Provider', 'discord', ['discord', 'twitch', 'steam', 'spotify', 'google', 'facebook'])
        ->expectsQuestion('Client ID (required)', 'the-id')
        ->expectsQuestion('Client Secret (required)', 'the-secret')
        ->expectsConfirmation('Enable provider?', 'no')
        ->assertSuccessful();

    expect(discord()->enabled)->toBeFalse();
});

it('keeps an existing secret on blank input', function () {
    $this->artisan('providers:configure', ['provider' => 'discord'])
        ->expectsQuestion('Client ID (required)', 'the-id')
        ->expectsQuestion('Client Secret (required)', 'the-secret')
        ->expectsConfirmation('Enable provider?', 'no')
        ->assertSuccessful();

    $this->artisan('providers:configure', ['provider' => 'discord'])
        ->expectsQuestion('Client ID (required)', 'new-id')
        ->expectsQuestion('Client Secret (required) [leave blank to keep]', '')
        ->expectsConfirmation('Enable provider?', 'no')
        ->assertSuccessful();

    expect(discord()->settings()->whereCode('client_secret')->firstOrFail()->value)->toBe('the-secret')
        ->and(discord()->settings()->whereCode('client_id')->firstOrFail()->value)->toBe('new-id');
});

it('fails on an unknown provider', function () {
    $this->artisan('providers:configure', ['provider' => 'nope'])->assertFailed();
});

it('refuses to enable with missing required settings', function () {
    $this->artisan('providers:configure', ['provider' => 'discord'])
        ->expectsQuestion('Client ID (required)', '')
        ->expectsQuestion('Client Secret (required)', '')
        ->expectsConfirmation('Enable provider?', 'yes')
        ->expectsConfirmation('Allow login with it?', 'yes')
        ->assertFailed();

    expect(discord()->enabled)->toBeFalse();
});
