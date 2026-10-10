<?php

use App\Domain\Playback\Control;
use App\Domain\Playback\Data\PlaybackState;
use App\Domain\Playback\Exceptions\PlayerDisconnectedException;
use App\Domain\Playback\Exceptions\UnsupportedControl;
use App\Domain\Playback\FeedMode;
use App\Domain\Playback\PlaybackStatus;
use App\Domain\Playback\Players\PollingPlayer;
use App\Domain\Playback\Testing\FakePlayer;
use Carbon\CarbonImmutable;

dataset('players', [
    'ahead' => [fn () => new FakePlayer(feedMode: FeedMode::Ahead), FeedMode::Ahead],
    'just-in-time' => [fn () => new FakePlayer(feedMode: FeedMode::JustInTime), FeedMode::JustInTime],
    'limited controls' => [fn () => new FakePlayer(supportedControls: [Control::Play, Control::Pause]), FeedMode::Ahead],
]);

beforeEach(fn () => CarbonImmutable::setTestNow('2026-01-01 12:00:00'));
afterEach(fn () => CarbonImmutable::setTestNow());

it('declares its feed mode and stays stopped initially', function (Closure $make, FeedMode $mode) {
    $player = $make();

    expect($player->feedMode())->toBe($mode)
        ->and($player->state()->status)->toBe(PlaybackStatus::Stopped)
        ->and($player->state()->currentTrack)->toBeNull()
        ->and($player->kind())->toBe('fake')
        ->and($player->compatibleProviders())->toBe(['fake'])
        ->and($player->requiresHostAccount())->toBeFalse();
})->with('players');

it('reports emitted state', function (Closure $make) {
    $player = $make();
    $seen = [];
    $player->onStateChanged(function (PlaybackState $s) use (&$seen) {
        $seen[] = $s;
    });
    $state = new PlaybackState(PlaybackStatus::Paused, null, 5000, CarbonImmutable::now());

    $player->emitState($state);

    expect($player->state()->status)->toBe($state->status)
        ->and($player->state()->positionMs)->toBe($state->positionMs)
        ->and($seen)->toBe([$state]);
})->with('players');

it('signals track changes to listeners and updates state', function (Closure $make) {
    $player = $make();
    $changes = [];
    $player->onTrackChanged(function (PlaybackState $s) use (&$changes) {
        $changes[] = $s->currentTrack->providerTrackId;
    });

    $player->emitTrackChanged('fake', 'track-1');
    $player->emitTrackChanged('fake', 'track-2');

    expect($changes)->toBe(['track-1', 'track-2'])
        ->and($player->state()->status)->toBe(PlaybackStatus::Playing)
        ->and($player->state()->currentTrack->providerTrackId)->toBe('track-2')
        ->and($player->state()->positionMs)->toBe(0)
        ->and($player->state()->updatedAt->equalTo(CarbonImmutable::now()))->toBeTrue();
})->with('players');

it('records enqueue commands', function (Closure $make) {
    $player = $make();

    $player->enqueue('fake', 'track-9');

    $command = $player->commands()[0];
    expect($player->commands())->toHaveCount(1)
        ->and($command->type)->toBe('enqueue')
        ->and($command->providerTrackId)->toBe('track-9')
        ->and($command->at->equalTo(CarbonImmutable::now()))->toBeTrue();
})->with('players');

it('refuses unsupported controls with a clear message', function () {
    $player = new FakePlayer(supportedControls: [Control::Play]);

    expect($player->supports(Control::Play))->toBeTrue()
        ->and($player->supports(Control::Skip))->toBeFalse()
        ->and(fn () => $player->pause())->toThrow(UnsupportedControl::class, 'pause is unsupported by this Player')
        ->and(fn () => $player->skip())->toThrow(UnsupportedControl::class, 'skip is unsupported by this Player')
        ->and(fn () => $player->seek(1000))->toThrow(UnsupportedControl::class, 'seek is unsupported by this Player')
        ->and(fn () => $player->volume(50))->toThrow(UnsupportedControl::class, 'volume is unsupported by this Player')
        ->and($player->commands())->toBe([]);
});

it('executes supported controls', function () {
    $player = new FakePlayer;

    $player->play();
    $player->seek(4000);
    $player->volume(30);
    $player->pause();
    $player->skip();

    expect(array_map(fn ($c) => $c->type, $player->commands()))->toBe(['play', 'seek', 'volume', 'pause', 'skip'])
        ->and($player->commands()[1]->value)->toBe(4000)
        ->and($player->commands()[2]->value)->toBe(30)
        ->and($player->state()->status)->toBe(PlaybackStatus::Paused);
});

it('rejects commands while disconnected and resumes after reconnect', function (Closure $make) {
    $player = $make();
    $player->disconnect();

    expect($player->isConnected())->toBeFalse()
        ->and(fn () => $player->enqueue('fake', 'track-1'))->toThrow(PlayerDisconnectedException::class)
        ->and(fn () => $player->play())->toThrow(PlayerDisconnectedException::class)
        ->and($player->commands())->toBe([]);

    $player->reconnect();
    $player->enqueue('fake', 'track-1');

    expect($player->isConnected())->toBeTrue()->and($player->commands())->toHaveCount(1);
})->with('players');

it('exposes the same Player contract for every kind', function (Closure $make) {
    $player = $make();

    expect($player->feedMode())->toBe(FeedMode::Ahead)
        ->and($player->state()->status)->toBe(PlaybackStatus::Stopped)
        ->and($player->compatibleProviders())->not->toBeEmpty()
        ->and($player->supports(Control::Skip))->toBeBool()
        ->and($player->kind())->toBeString();
})->with([
    'fake' => [fn () => new FakePlayer],
    'polling' => [fn () => app(PollingPlayer::class)],
]);
