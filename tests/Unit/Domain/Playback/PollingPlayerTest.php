<?php

use App\Domain\Playback\Control;
use App\Domain\Playback\Data\PlaybackState;
use App\Domain\Playback\Data\TrackReference;
use App\Domain\Playback\Exceptions\PlayerDisconnectedException;
use App\Domain\Playback\Exceptions\UnsupportedControl;
use App\Domain\Playback\FeedMode;
use App\Domain\Playback\Jobs\PollPlayback;
use App\Domain\Playback\PlaybackStatus;
use App\Domain\Playback\Players\PollingPlayer;
use App\Models\Party;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-01-01 12:00:00');
    Cache::flush();
    $this->party = new Party;
    $this->party->code = 'ABCD';
    $this->player = app(PollingPlayer::class)->forParty($this->party);
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('is an ahead player for Spotify that needs a Host account and has no direct controls', function () {
    expect($this->player->kind())->toBe('polling')
        ->and($this->player->feedMode())->toBe(FeedMode::Ahead)
        ->and($this->player->requiresHostAccount())->toBeTrue()
        ->and($this->player->compatibleProviders())->toBe(['spotify'])
        ->and($this->player->state()->status)->toBe(PlaybackStatus::Stopped);

    foreach (Control::cases() as $control) {
        expect($this->player->supports($control))->toBeFalse();
    }

    expect(fn () => $this->player->pause())->toThrow(UnsupportedControl::class)
        ->and(fn () => $this->player->skip())->toThrow(UnsupportedControl::class)
        ->and(fn () => $this->player->play())->toThrow(UnsupportedControl::class)
        ->and(fn () => $this->player->seek(1))->toThrow(UnsupportedControl::class)
        ->and(fn () => $this->player->volume(1))->toThrow(UnsupportedControl::class);
});

it('persists the last seen state and track in the cache rather than memory', function () {
    $state = new PlaybackState(PlaybackStatus::Playing, new TrackReference('spotify', 't1'), 2500, CarbonImmutable::now(), 90000);

    $this->player->remember($state);
    $this->player->markSeen('t1');
    $other = app(PollingPlayer::class)->forParty($this->party);

    expect($other->state())->toEqual($state)
        ->and($other->lastSeenTrackId())->toBe('t1');

    $other->markSeen(null);
    expect($this->player->lastSeenTrackId())->toBeNull();
});

it('cannot enqueue without a bound party', function () {
    app(PollingPlayer::class)->enqueue('spotify', 't1');
})->throws(PlayerDisconnectedException::class);

it('computes the poll delay from playback state', function (PlaybackState $state, int $seconds) {
    config(['musicparty.polling' => ['min_delay_seconds' => 2, 'normal_delay_seconds' => 10, 'idle_delay_seconds' => 60, 'backoff_cap_seconds' => 300]]);

    expect(PollPlayback::delayFor($state))->toBe($seconds);
})->with([
    'mid track' => [fn () => new PlaybackState(PlaybackStatus::Playing, new TrackReference('s', 't'), 30000, null, 180000), 10],
    'unknown duration' => [fn () => new PlaybackState(PlaybackStatus::Playing, new TrackReference('s', 't')), 10],
    'near the end' => [fn () => new PlaybackState(PlaybackStatus::Playing, new TrackReference('s', 't'), 176000, null, 180000), 5],
    'past the end' => [fn () => new PlaybackState(PlaybackStatus::Playing, new TrackReference('s', 't'), 181000, null, 180000), 2],
    'paused' => [fn () => new PlaybackState(PlaybackStatus::Paused, new TrackReference('s', 't'), 0, null, 180000), 60],
    'idle' => [PlaybackState::stopped(), 60],
]);

it('backs off exponentially up to a cap and honours retry-after', function (int $failures, ?int $retryAfter, int $seconds) {
    config(['musicparty.polling' => ['min_delay_seconds' => 2, 'normal_delay_seconds' => 10, 'idle_delay_seconds' => 60, 'backoff_cap_seconds' => 300]]);

    expect(PollPlayback::backoffDelay($failures, $retryAfter))->toBe($seconds);
})->with([
    'first' => [1, null, 10],
    'second' => [2, null, 20],
    'fourth' => [4, null, 80],
    'capped' => [9, null, 300],
    'huge streak' => [500, null, 300],
    'retry-after longer' => [1, 120, 120],
    'retry-after shorter' => [3, 5, 40],
    'retry-after beyond cap' => [9, 900, 900],
]);
