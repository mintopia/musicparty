<?php

namespace App\Services\Spotify;

use App\Exceptions\ProviderBackoffException;
use SpotifyWebAPI\SpotifyWebAPIException;

class SpotifyBackoffException extends SpotifyWebAPIException implements ProviderBackoffException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct('Spotify rate limit backoff active', self::RATE_LIMIT_STATUS);
    }

    public function retryAfterSeconds(): int
    {
        return $this->retryAfterSeconds;
    }
}
