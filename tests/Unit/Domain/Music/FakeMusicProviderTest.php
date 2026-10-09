<?php

use App\Domain\Music\Exceptions\ProviderTemporaryFailure;
use App\Domain\Music\Testing\FakeMusicProvider;

it('records appended tracks per playlist', function () {
    $provider = FakeMusicProvider::withDefaultCatalogue();

    $provider->appendToPlaylist('history', ['track-1']);
    $provider->appendToPlaylist('history', ['track-2', 'track-3']);

    expect($provider->appendedTo('history'))->toBe(['track-1', 'track-2', 'track-3'])
        ->and($provider->appendedTo('other'))->toBe([]);
});

it('injects a failure into any operation and only once', function () {
    $provider = FakeMusicProvider::withDefaultCatalogue();

    $provider->failNextWith(new ProviderTemporaryFailure('boom'));
    expect(fn () => $provider->playlists('acct'))->toThrow(ProviderTemporaryFailure::class, 'boom');

    $provider->failNextWith(new ProviderTemporaryFailure('boom'));
    expect(fn () => $provider->appendToPlaylist('p', ['track-1']))->toThrow(ProviderTemporaryFailure::class);
    expect($provider->appendedTo('p'))->toBe([]);

    expect($provider->playlists('acct'))->not->toBeEmpty();
});

it('uses an empty catalogue by default and a custom id', function () {
    $provider = new FakeMusicProvider(id: 'other');

    expect($provider->id())->toBe('other')
        ->and($provider->search('a', 10, 0)->total)->toBe(0);
});
