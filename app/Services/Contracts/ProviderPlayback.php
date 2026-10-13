<?php

namespace App\Services\Contracts;

use App\Services\Music\Data\Device;
use App\Services\Music\Data\PlaybackState;
use App\Services\Music\Data\Track;

interface ProviderPlayback
{
    public function currentPlayback(): ?PlaybackState;

    public function sendToPlayer(Track $track): void;

    /**
     * How many tracks are queued to play after the current one, excluding any padding the provider adds to its queue. Negative when nothing is playing.
     */
    public function queueDepth(): int;

    public function transferPlayback(string $deviceId): void;

    public function play(?string $deviceId = null, ?Track $track = null): void;

    public function pause(): void;

    public function next(): void;

    public function previous(): void;

    public function seek(int $positionMs): void;

    /**
     * @return list<Device>
     */
    public function devices(): array;
}
