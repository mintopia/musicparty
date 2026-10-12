<?php

use App\Domain\Music\Providers\SpotifyMusicProvider;
use App\Domain\Party\PairingCatalogue;
use App\Domain\Playback\Players\PollingPlayer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
});

afterEach(function (): void {
    app()->detectEnvironment(fn (): string => 'testing');
});

/**
 * @return list<string>
 */
function providerIds(): array
{
    return array_column(app(PairingCatalogue::class)->providers(), 'id');
}

it('lists Spotify when it is enabled and configured', function (): void {
    configureSpotifyCredentials();

    expect(providerIds())->toContain('spotify');
});

it('does not list Spotify when it is disabled', function (): void {
    configureSpotifyCredentials();
    setPairingToggle('music_provider.spotify.enabled', false);

    expect(providerIds())->not->toContain('spotify');
});

it('does not list Spotify when it is enabled but the secret is blank', function (): void {
    configureSpotifyCredentials();
    blankSpotifyCredential('client_secret');

    expect(providerIds())->not->toContain('spotify');
});

it('does not list Spotify when it is not configured at all', function (): void {
    expect(providerIds())->not->toContain('spotify')->toContain('fake');
});

it('ignores the Spotify social provider enabled flag', function (): void {
    configureSpotifyCredentials()->forceFill(['enabled' => false])->save();
    app()->forgetScopedInstances();

    expect(providerIds())->toContain('spotify');
});

it('hides the fake provider in production even when enabled and lists it locally', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    expect(providerIds())->not->toContain('fake')
        ->and(array_column(app(PairingCatalogue::class)->registeredProviders(), 'id'))->not->toContain('fake')
        ->and(app(PairingCatalogue::class)->isProviderAvailable('fake'))->toBeFalse();

    app()->detectEnvironment(fn (): string => 'local');
    expect(providerIds())->toContain('fake');
});

it('leaves out a disabled player', function (): void {
    configureSpotifyCredentials();
    setPairingToggle('player.browser.enabled', false);

    $kinds = array_column(app(PairingCatalogue::class)->players(), 'kind');

    expect($kinds)->not->toContain('browser')->toContain('polling', 'soloist');
});

it('leaves out players with no available compatible provider and intersects the rest', function (): void {
    expect(app(PairingCatalogue::class)->players())->toBe([]);

    configureSpotifyCredentials();
    $players = app(PairingCatalogue::class)->players();

    expect(array_column($players, 'kind'))->toBe(['polling', 'browser', 'soloist'])
        ->and($players[0]['compatibleProviders'])->toBe(['spotify']);
});

it('reflects a toggle flipped between two calls on the same instance', function (): void {
    configureSpotifyCredentials();
    $catalogue = app(PairingCatalogue::class);
    expect(array_column($catalogue->providers(), 'id'))->toContain('spotify');

    setPairingToggle('music_provider.spotify.enabled', false);
    expect(array_column($catalogue->providers(), 'id'))->not->toContain('spotify')
        ->and($catalogue->isPlayerAvailable('polling', 'spotify'))->toBeFalse();

    setPairingToggle('music_provider.spotify.enabled', true);
    expect($catalogue->isPlayerAvailable('polling', 'spotify'))->toBeTrue();
});

it('checks player availability against the provider pairing', function (): void {
    configureSpotifyCredentials();
    $catalogue = app(PairingCatalogue::class);

    expect($catalogue->isPlayerAvailable('polling', 'fake'))->toBeFalse()
        ->and($catalogue->isPlayerAvailable('missing', 'spotify'))->toBeFalse()
        ->and($catalogue->isProviderAvailable('missing'))->toBeFalse();
});

it('describes every registered entry for the admin page', function (): void {
    setPairingToggle('player.soloist.enabled', false);
    $catalogue = app(PairingCatalogue::class);

    $spotify = collect($catalogue->registeredProviders())->firstWhere('id', 'spotify');
    $soloist = collect($catalogue->registeredPlayers())->firstWhere('kind', 'soloist');

    expect($spotify)->toMatchArray(['enabled' => true, 'configured' => false, 'requires' => ['spotify'], 'localOnly' => false])
        ->and(collect($catalogue->registeredProviders())->firstWhere('id', 'fake'))->toMatchArray(['localOnly' => true])
        ->and($soloist)->toMatchArray(['enabled' => false, 'compatibleProviders' => ['spotify']])
        ->and(array_column($catalogue->registeredPlayers(), 'kind'))->toContain('polling', 'browser', 'soloist');
});

it('still resolves registered classes while disabled', function (): void {
    setPairingToggle('music_provider.spotify.enabled', false);
    setPairingToggle('player.polling.enabled', false);
    $catalogue = app(PairingCatalogue::class);

    expect($catalogue->provider('spotify'))->toBeInstanceOf(SpotifyMusicProvider::class)
        ->and($catalogue->player('polling'))->toBeInstanceOf(PollingPlayer::class)
        ->and($catalogue->trackUrl('spotify', 'abc'))->toBeString()
        ->and($catalogue->playlistUrl('spotify', 'abc'))->toBeString();
});
