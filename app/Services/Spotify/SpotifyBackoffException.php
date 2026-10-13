<?php

namespace App\Services\Spotify;

use SpotifyWebAPI\SpotifyWebAPIException;

class SpotifyBackoffException extends SpotifyWebAPIException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct('Spotify rate limit backoff active', self::RATE_LIMIT_STATUS);
    }
}
