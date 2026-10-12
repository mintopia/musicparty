<?php

namespace App\Domain\Playback\Testing;

use App\Domain\Music\Exceptions\ProviderTemporaryFailure;
use App\Domain\Playback\Contracts\PlaybackClient;
use App\Domain\Playback\Data\PlaybackDevice;
use App\Domain\Playback\Data\PlaybackState;
use Throwable;

class FakePlaybackClient implements PlaybackClient
{
    private ?Throwable $nextFailure = null;

    private ?PlaybackState $playback = null;

    /** @var list<string> */
    private array $queued = [];

    /** @var list<array{0: string, 1: int|null}> */
    private array $commands = [];

    /** @var list<string|null> */
    private array $commandDevices = [];

    /** @var list<PlaybackDevice> */
    private array $devices = [];

    public function playbackIs(PlaybackState $state): self
    {
        $this->playback = $state;

        return $this;
    }

    public function failNextWith(Throwable $failure): self
    {
        $this->nextFailure = $failure;

        return $this;
    }

    public function rateLimitNext(int $retryAfterSeconds): self
    {
        return $this->failNextWith(new ProviderTemporaryFailure('The Music Provider is rate limiting requests.', $retryAfterSeconds));
    }

    public function currentPlayback(string $hostAccountId): PlaybackState
    {
        $this->throwInjectedFailure();

        return $this->playback ?? PlaybackState::stopped();
    }

    public function queueTrack(string $providerTrackId, string $hostAccountId, ?string $deviceId = null): void
    {
        $this->throwInjectedFailure();

        $this->queued[] = $providerTrackId;
    }

    public function play(string $hostAccountId, ?string $deviceId = null): void
    {
        $this->record('play', null, $deviceId);
    }

    public function pause(string $hostAccountId, ?string $deviceId = null): void
    {
        $this->record('pause', null, $deviceId);
    }

    public function next(string $hostAccountId, ?string $deviceId = null): void
    {
        $this->record('next', null, $deviceId);
    }

    public function previous(string $hostAccountId, ?string $deviceId = null): void
    {
        $this->record('previous', null, $deviceId);
    }

    public function seek(int $positionMs, string $hostAccountId, ?string $deviceId = null): void
    {
        $this->record('seek', $positionMs, $deviceId);
    }

    public function volume(int $percent, string $hostAccountId, ?string $deviceId = null): void
    {
        $this->record('volume', $percent, $deviceId);
    }

    /**
     * @param  list<PlaybackDevice>  $devices
     */
    public function devicesAre(array $devices): self
    {
        $this->devices = $devices;

        return $this;
    }

    public function devices(string $hostAccountId): array
    {
        $this->throwInjectedFailure();

        return $this->devices;
    }

    /**
     * @return list<string|null>
     */
    public function commandDevices(): array
    {
        return $this->commandDevices;
    }

    /**
     * @return list<array{0: string, 1: int|null}>
     */
    public function commands(): array
    {
        return $this->commands;
    }

    /**
     * @return list<string>
     */
    public function queuedTracks(): array
    {
        return $this->queued;
    }

    private function record(string $command, ?int $value = null, ?string $deviceId = null): void
    {
        $this->throwInjectedFailure();

        $this->commands[] = [$command, $value];
        $this->commandDevices[] = $deviceId;
    }

    private function throwInjectedFailure(): void
    {
        if ($this->nextFailure === null) {
            return;
        }

        $failure = $this->nextFailure;
        $this->nextFailure = null;

        throw $failure;
    }
}
