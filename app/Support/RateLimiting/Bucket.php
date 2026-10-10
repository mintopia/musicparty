<?php

namespace App\Support\RateLimiting;

interface Bucket
{
    /**
     * @return int Milliseconds until the next attempt is allowed; 0 when this attempt was admitted.
     */
    public function attempt(string $key, int $burst, float $perSecond): int;
}
