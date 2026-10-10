<?php

namespace App\Domain\Playback\Contracts;

use App\Domain\Music\Exceptions\ProviderTemporaryFailure;
use App\Domain\Music\Exceptions\ProviderUnavailableException;
use App\Domain\Playback\Data\PlaybackState;

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
}
