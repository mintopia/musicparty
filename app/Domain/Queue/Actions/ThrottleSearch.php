<?php

namespace App\Domain\Queue\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Queue\Exceptions\SearchRateLimitedException;
use App\Support\RateLimiting\Bucket;

class ThrottleSearch
{
    public function __construct(private readonly Bucket $bucket) {}

    public function __invoke(User $user): void
    {
        $waitMs = $this->bucket->attempt(
            "search:{$user->id}",
            (int) config('musicparty.search_rate_limit.burst'),
            (float) config('musicparty.search_rate_limit.per_second'),
        );

        if ($waitMs > 0) {
            throw new SearchRateLimitedException(max(1, (int) ceil($waitMs / 1000)));
        }
    }
}
