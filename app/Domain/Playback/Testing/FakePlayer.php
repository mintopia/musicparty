<?php

namespace App\Domain\Playback\Testing;

use App\Domain\Playback\Contracts\Player;
use App\Domain\Playback\Control;
use App\Domain\Playback\Data\PlaybackState;
use App\Domain\Playback\Data\PlayerCommand;
use App\Domain\Playback\Data\TrackReference;
use App\Domain\Playback\Exceptions\PlayerDisconnectedException;
use App\Domain\Playback\Exceptions\UnsupportedControl;
use App\Domain\Playback\FeedMode;
use App\Domain\Playback\PlaybackStatus;
use Carbon\CarbonImmutable;
use Closure;

class FakePlayer implements Player
{
    private PlaybackState $state;

    private bool $connected = true;

    /** @var list<TrackReference> */
    private array $queue = [];

    /** @var list<PlayerCommand> */
    private array $commands = [];

    /** @var list<Closure(PlaybackState): void> */
    private array $trackChangedListeners = [];

    /** @var list<Closure(PlaybackState): void> */
    private array $stateListeners = [];

    /**
     * @param  list<string>  $compatibleProviders
     * @param  list<Control>  $supportedControls
     */
    public function __construct(
        private readonly string $kind = 'fake',
        private readonly array $compatibleProviders = ['fake'],
        private readonly FeedMode $feedMode = FeedMode::Ahead,
        private readonly bool $requiresHostAccount = false,
        private readonly array $supportedControls = [Control::Play, Control::Pause, Control::Skip, Control::Seek, Control::Volume],
    ) {
        $this->state = PlaybackState::stopped();
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public function compatibleProviders(): array
    {
        return $this->compatibleProviders;
    }

    public function feedMode(): FeedMode
    {
        return $this->feedMode;
    }

    public function requiresHostAccount(): bool
    {
        return $this->requiresHostAccount;
    }

    public function supports(Control $control): bool
    {
        return in_array($control, $this->supportedControls, true);
    }

    public function state(): PlaybackState
    {
        return $this->state;
    }

    /**
     * @param  Closure(PlaybackState): void  $listener
     */
    public function onTrackChanged(Closure $listener): self
    {
        $this->trackChangedListeners[] = $listener;

        return $this;
    }

    /**
     * @param  Closure(PlaybackState): void  $listener
     */
    public function onStateChanged(Closure $listener): self
    {
        $this->stateListeners[] = $listener;

        return $this;
    }

    public function emitTrackChanged(string $providerId, string $providerTrackId): void
    {
        $this->state = new PlaybackState(
            PlaybackStatus::Playing,
            new TrackReference($providerId, $providerTrackId),
            0,
            CarbonImmutable::now(),
        );

        foreach ($this->trackChangedListeners as $listener) {
            $listener($this->state);
        }
    }

    public function emitState(PlaybackState $state): void
    {
        $this->state = $state;

        foreach ($this->stateListeners as $listener) {
            $listener($state);
        }
    }

    public function disconnect(): void
    {
        $this->connected = false;
    }

    public function reconnect(): void
    {
        $this->connected = true;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * @return list<PlayerCommand>
     */
    public function commands(): array
    {
        return $this->commands;
    }

    public function enqueue(string $providerId, string $providerTrackId): void
    {
        $this->record('enqueue', $providerTrackId);
        $this->queue[] = new TrackReference($providerId, $providerTrackId);

        if ($this->state->currentTrack === null) {
            $this->advance();
        }
    }

    /**
     * @return list<TrackReference>
     */
    public function queued(): array
    {
        return $this->queue;
    }

    /**
     * Simulates the current Track ending: the next enqueued Track starts, otherwise playback stops.
     */
    public function advance(): void
    {
        $next = array_shift($this->queue);

        if ($next === null) {
            $this->emitState(PlaybackState::stopped());

            return;
        }

        $this->emitTrackChanged($next->providerId, $next->providerTrackId);
    }

    /**
     * Simulates the Provider autoplaying a Track that the Party never enqueued.
     */
    public function autoplay(string $providerId, string $providerTrackId): void
    {
        $this->emitTrackChanged($providerId, $providerTrackId);
    }

    public function play(): void
    {
        $this->control(Control::Play);
        $this->setStatus(PlaybackStatus::Playing);
    }

    public function pause(): void
    {
        $this->control(Control::Pause);
        $this->setStatus(PlaybackStatus::Paused);
    }

    public function skip(): void
    {
        $this->control(Control::Skip);

        if ($this->queue !== []) {
            $this->advance();
        }
    }

    public function seek(int $positionMs): void
    {
        $this->control(Control::Seek, $positionMs);
    }

    public function volume(int $level): void
    {
        $this->control(Control::Volume, $level);
    }

    private function control(Control $control, ?int $value = null): void
    {
        if (! $this->supports($control)) {
            throw UnsupportedControl::for($control);
        }

        $this->record($control->value, null, $value);
    }

    private function record(string $type, ?string $providerTrackId = null, ?int $value = null): void
    {
        if (! $this->connected) {
            throw new PlayerDisconnectedException;
        }

        $this->commands[] = new PlayerCommand($type, $providerTrackId, CarbonImmutable::now(), $value);
    }

    private function setStatus(PlaybackStatus $status): void
    {
        $this->state = new PlaybackState($status, $this->state->currentTrack, $this->state->positionMs, CarbonImmutable::now());
    }
}
