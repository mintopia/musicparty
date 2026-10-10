<?php

namespace App\Domain\Queue\Exceptions;

class SearchRateLimitedException extends RequestRefusedException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct(
            "You are searching too quickly. Try again in {$retryAfterSeconds} ".($retryAfterSeconds === 1 ? 'second' : 'seconds').'.',
            self::SEARCH_RATE_LIMITED,
        );
    }
}
