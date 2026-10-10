<?php

namespace App\Domain\Playback\Exceptions;

use RuntimeException;

/**
 * The Music Provider is backing off, so the call was refused without being attempted.
 */
class PlayerRateLimitedException extends RuntimeException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct("The Music Provider is rate limiting requests. Retry in {$retryAfterSeconds} seconds.");
    }
}
