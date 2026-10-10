<?php

use App\Domain\Playback\PlayerFactory;
use App\Domain\Playback\Players\BrowserPlayer;
use App\Domain\Playback\Players\PollingPlayer;
use App\Domain\Playback\Players\SoloistPlayer;
use App\Domain\Playback\Testing\FakePlayer;

it('builds each production Player kind from the catalogue', function (string $kind, string $class) {
    expect(app(PlayerFactory::class)->make($kind) instanceof $class)->toBeTrue();
})->with([
    'polling' => ['polling', PollingPlayer::class],
    'browser' => ['browser', BrowserPlayer::class],
    'soloist' => ['soloist', SoloistPlayer::class],
]);

it('rejects an unknown Player kind', function () {
    app(PlayerFactory::class)->make('nonexistent');
})->throws(InvalidArgumentException::class);

it('resolves the Fake Player in the testing environment', function () {
    expect(app(PlayerFactory::class)->make('fake'))->toBeInstanceOf(FakePlayer::class);
});

it('does not resolve the Fake Player outside the testing environment', function (string $environment) {
    app()->detectEnvironment(fn (): string => $environment);

    expect(fn () => app(PlayerFactory::class)->make('fake'))->toThrow(LogicException::class)
        ->and(fn () => new FakePlayer)->toThrow(LogicException::class);
})->with(['production', 'local']);

it('does not list the Fake Player in the production catalogue', function () {
    $config = require base_path('config/musicparty.php');

    expect($config['players'])->not->toHaveKey('fake');
});
