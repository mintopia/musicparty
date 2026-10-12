<?php

namespace App\Domain\Playback\Contracts;

use App\Domain\Music\Exceptions\ProviderTemporaryFailure;
use App\Domain\Music\Exceptions\ProviderUnavailableException;
use App\Domain\Playback\Data\PlaybackDevice;
use App\Domain\Playback\Data\PlaybackState;
use App\Domain\Playback\Exceptions\PlayerCommandRejectedException;

interface PlaybackClient
{
    /**
     * @throws ProviderUnavailableException
     * @throws ProviderTemporaryFailure
     */
    public function currentPlayback(string $hostAccountId): PlaybackState;

    /**
     * @throws ProviderUnavailableException
     * @throws ProviderTemporaryFailure
     */
    public function queueTrack(string $providerTrackId, string $hostAccountId, ?string $deviceId = null): void;

    /**
     * @throws ProviderUnavailableException
     * @throws ProviderTemporaryFailure
     * @throws PlayerCommandRejectedException
     */
    public function play(string $hostAccountId, ?string $deviceId = null): void;

    /**
     * @throws ProviderUnavailableException
     * @throws ProviderTemporaryFailure
     * @throws PlayerCommandRejectedException
     */
    public function pause(string $hostAccountId, ?string $deviceId = null): void;

    /**
     * @throws ProviderUnavailableException
     * @throws ProviderTemporaryFailure
     * @throws PlayerCommandRejectedException
     */
    public function next(string $hostAccountId, ?string $deviceId = null): void;

    /**
     * @throws ProviderUnavailableException
     * @throws ProviderTemporaryFailure
     * @throws PlayerCommandRejectedException
     */
    public function seek(int $positionMs, string $hostAccountId, ?string $deviceId = null): void;

    /**
     * @throws ProviderUnavailableException
     * @throws ProviderTemporaryFailure
     * @throws PlayerCommandRejectedException
     */
    public function volume(int $percent, string $hostAccountId, ?string $deviceId = null): void;

    /**
     * @return list<PlaybackDevice>
     *
     * @throws ProviderUnavailableException
     * @throws ProviderTemporaryFailure
     */
    public function devices(string $hostAccountId): array;
}
