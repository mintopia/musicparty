<?php

namespace App\Domain\Playback\Exceptions;

/**
 * Spotify no longer lists the playback device the Party chose.
 */
class PlaybackDeviceNotFoundException extends PlayerCommandRejectedException
{
    public function __construct(public readonly string $deviceId)
    {
        parent::__construct('Spotify cannot find the chosen playback device.');
    }
}
