<?php

namespace App\Domain\Playback\Contracts;

use App\Domain\Music\Exceptions\ProviderTemporaryFailure;
use App\Domain\Music\Exceptions\ProviderUnavailableException;
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
    public function queueTrack(string $providerTrackId, string $hostAccountId): void;

    /**
     * @throws ProviderUnavailableException
     * @throws ProviderTemporaryFailure
     * @throws PlayerCommandRejectedException
     */
    public function play(string $hostAccountId): void;

    /**
     * @throws ProviderUnavailableException
     * @throws ProviderTemporaryFailure
     * @throws PlayerCommandRejectedException
     */
    public function pause(string $hostAccountId): void;

    /**
     * @throws ProviderUnavailableException
     * @throws ProviderTemporaryFailure
     * @throws PlayerCommandRejectedException
     */
    public function next(string $hostAccountId): void;

    /**
     * @throws ProviderUnavailableException
     * @throws ProviderTemporaryFailure
     * @throws PlayerCommandRejectedException
     */
    public function seek(int $positionMs, string $hostAccountId): void;

    /**
     * @throws ProviderUnavailableException
     * @throws ProviderTemporaryFailure
     * @throws PlayerCommandRejectedException
     */
    public function volume(int $percent, string $hostAccountId): void;
}
