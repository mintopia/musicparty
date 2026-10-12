<?php

namespace App\Domain\Playback\Exceptions;

use RuntimeException;

/**
 * The Host's Spotify control link is missing or lacks the playback scopes, so devices cannot be listed.
 */
class PlaybackDevicesUnavailableException extends RuntimeException
{
    public function __construct(string $message = 'Re-link your Spotify account to list playback devices.')
    {
        parent::__construct($message);
    }
}
