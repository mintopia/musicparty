<?php

namespace App\Domain\Playback\Testing;

use App\Domain\Music\Exceptions\ProviderTemporaryFailure;
use App\Domain\Playback\Contracts\PlaybackClient;
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

    public function queueTrack(string $providerTrackId, string $hostAccountId): void
    {
        $this->throwInjectedFailure();

        $this->queued[] = $providerTrackId;
    }

    public function play(string $hostAccountId): void
    {
        $this->record('play');
    }

    public function pause(string $hostAccountId): void
    {
        $this->record('pause');
    }

    public function next(string $hostAccountId): void
    {
        $this->record('next');
    }

    public function seek(int $positionMs, string $hostAccountId): void
    {
        $this->record('seek', $positionMs);
    }

    public function volume(int $percent, string $hostAccountId): void
    {
        $this->record('volume', $percent);
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

    private function record(string $command, ?int $value = null): void
    {
        $this->throwInjectedFailure();

        $this->commands[] = [$command, $value];
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
