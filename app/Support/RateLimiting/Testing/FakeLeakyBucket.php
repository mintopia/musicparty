<?php

namespace App\Support\RateLimiting\Testing;

use App\Support\RateLimiting\Bucket;
use Illuminate\Support\Carbon;

class FakeLeakyBucket implements Bucket
{
    /** @var array<string, float> */
    private array $theoreticalArrivals = [];

    public function attempt(string $key, int $burst, float $perSecond): int
    {
        $interval = 1000 / $perSecond;
        $now = (float) Carbon::now()->getTimestampMs();
        $arrival = max($this->theoreticalArrivals[$key] ?? $now, $now);
        $allowAt = $arrival - $interval * ($burst - 1);

        if ($now < $allowAt) {
            return (int) ceil($allowAt - $now);
        }

        $this->theoreticalArrivals[$key] = $arrival + $interval;

        return 0;
    }
}
