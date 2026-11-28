<?php

namespace App\Mods\AiRequestReview;

use App\Models\Party;
use Illuminate\Support\Facades\Cache;

class AiReviewBreaker
{
    public const FAILURE_THRESHOLD = 3;

    public const OPEN_SECONDS = 60;

    public function isOpen(Party $party): bool
    {
        return Cache::has($this->openKey($party));
    }

    /**
     * @return array{open: bool, failures: int, threshold: int, openSeconds: int}
     */
    public function state(Party $party): array
    {
        return [
            'open' => $this->isOpen($party),
            'failures' => (int) Cache::get($this->failuresKey($party), 0),
            'threshold' => self::FAILURE_THRESHOLD,
            'openSeconds' => self::OPEN_SECONDS,
        ];
    }

    public function recordFailure(Party $party): void
    {
        $key = $this->failuresKey($party);
        Cache::add($key, 0, self::OPEN_SECONDS * 5);
        if ((int) Cache::increment($key) >= self::FAILURE_THRESHOLD) {
            Cache::put($this->openKey($party), true, self::OPEN_SECONDS);
            Cache::forget($key);
        }
    }

    public function recordSuccess(Party $party): void
    {
        Cache::forget($this->failuresKey($party));
    }

    protected function openKey(Party $party): string
    {
        return "ai-review.breaker.open.{$party->id}";
    }

    protected function failuresKey(Party $party): string
    {
        return "ai-review.breaker.failures.{$party->id}";
    }
}
