<?php

use App\Domain\Admin\Actions\EnsureSocialProvider;
use App\Domain\Identity\ProviderCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->credentials = fn (): ProviderCredentials => app(ProviderCredentials::class);
});

it('returns a stored encrypted secret decrypted', function () {
    configureSpotifyCredentials('spotify', 'the-id', 'the-secret');

    expect(($this->credentials)()->get('spotify', 'client_id'))->toBe('the-id')
        ->and(($this->credentials)()->get('spotify', 'client_secret'))->toBe('the-secret');
});

it('returns null for blank, missing and unknown values', function () {
    app(EnsureSocialProvider::class)->handle('spotify');

    expect(($this->credentials)()->get('spotify', 'client_id'))->toBeNull()
        ->and(($this->credentials)()->get('spotify', 'nonexistent'))->toBeNull()
        ->and(($this->credentials)()->get('spotifysearch', 'client_id'))->toBeNull();
});

it('reports configured only when every required setting is filled', function () {
    $provider = configureSpotifyCredentials('spotify');
    expect(($this->credentials)()->isConfigured('spotify'))->toBeTrue();

    $provider->settings->firstWhere('code', 'client_secret')?->forceFill(['value' => null])->save();
    app()->forgetScopedInstances();

    expect(($this->credentials)()->isConfigured('spotify'))->toBeFalse()
        ->and(($this->credentials)()->isConfigured('spotifysearch'))->toBeFalse();
});

it('reads the database once per scope', function () {
    configureSpotifyCredentials();
    $reader = ($this->credentials)();

    $queries = countQueries(function () use ($reader) {
        $reader->get('spotify', 'client_id');
        $reader->get('spotify', 'client_secret');
        $reader->isConfigured('spotify');
    });
    $repeat = countQueries(fn () => $reader->get('spotify', 'client_id'));

    expect($queries)->toBeGreaterThan(0)->and($repeat)->toBe(0);
});

it('sees an updated credential after the scope is flushed', function () {
    $provider = configureSpotifyCredentials('spotify', 'id', 'old-secret');
    expect(($this->credentials)()->get('spotify', 'client_secret'))->toBe('old-secret');

    $provider->settings->firstWhere('code', 'client_secret')?->forceFill(['value' => 'new-secret'])->save();
    expect(($this->credentials)()->get('spotify', 'client_secret'))->toBe('old-secret');

    app()->forgetScopedInstances();

    expect(($this->credentials)()->get('spotify', 'client_secret'))->toBe('new-secret');
});
