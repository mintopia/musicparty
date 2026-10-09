<?php

use App\Domain\Music\Testing\FakeMusicProvider;
use App\Domain\Playback\Actions\PairPlayer;
use App\Domain\Playback\Exceptions\IncompatibleProviderException;
use App\Domain\Playback\Testing\FakePlayer;

it('pairs a compatible provider', function () {
    $player = new FakePlayer(compatibleProviders: ['spotify', 'fake']);

    (new PairPlayer)($player, new FakeMusicProvider);

    expect(true)->toBeTrue();
});

it('rejects an incompatible provider and lists the compatible options', function () {
    $player = new FakePlayer(compatibleProviders: ['spotify', 'fake']);

    (new PairPlayer)($player, new FakeMusicProvider(id: 'x'));
})->throws(IncompatibleProviderException::class, "Player 'fake' is not compatible with Music Provider 'x'. Compatible: spotify, fake.");

it('rejects every provider when the compatible list is empty', function () {
    $player = new FakePlayer(compatibleProviders: []);

    (new PairPlayer)($player, new FakeMusicProvider);
})->throws(IncompatibleProviderException::class, 'Compatible: none.');
