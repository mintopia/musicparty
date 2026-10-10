<?php

namespace App\Domain\Playback\Contracts;

use App\Domain\Playback\Control;
use App\Domain\Playback\Data\PlaybackState;
use App\Domain\Playback\Exceptions\PlayerDisconnectedException;
use App\Domain\Playback\Exceptions\PlayerEnqueueUnconfirmedException;
use App\Domain\Playback\Exceptions\UnsupportedControl;
use App\Domain\Playback\FeedMode;

interface Player
{
    public function kind(): string;

    /**
     * @return list<string>
     */
    public function compatibleProviders(): array;

    public function feedMode(): FeedMode;

    public function requiresHostAccount(): bool;

    public function supports(Control $control): bool;

    public function state(): PlaybackState;

    /**
     * @throws PlayerDisconnectedException
     * @throws PlayerEnqueueUnconfirmedException
     */
    public function enqueue(string $providerId, string $providerTrackId): void;

    /**
     * @throws UnsupportedControl
     * @throws PlayerDisconnectedException
     */
    public function play(): void;

    /**
     * @throws UnsupportedControl
     * @throws PlayerDisconnectedException
     */
    public function pause(): void;

    /**
     * @throws UnsupportedControl
     * @throws PlayerDisconnectedException
     */
    public function skip(): void;

    /**
     * @throws UnsupportedControl
     * @throws PlayerDisconnectedException
     */
    public function seek(int $positionMs): void;

    /**
     * @throws UnsupportedControl
     * @throws PlayerDisconnectedException
     */
    public function volume(int $level): void;
}
