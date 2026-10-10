<?php

namespace App\Domain\Queue\Exceptions;

/**
 * The Music Provider is backing off after a rate limit; the Member is told when to retry.
 */
class ProviderRateLimitedException extends RequestRefusedException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct(
            "The music provider is busy. Try again in {$retryAfterSeconds} ".($retryAfterSeconds === 1 ? 'second' : 'seconds').'.',
            self::PROVIDER_UNAVAILABLE,
        );
    }
}
