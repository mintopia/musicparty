<?php

namespace App\Support;

use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use LogicException;

class LeakyBucket
{
    public function __construct(
        private readonly string $key,
        private readonly int $capacity,
        private readonly float $leakPerSecond,
        private readonly ?Repository $cache = null,
    ) {}

    /**
     * Adds one drop to the bucket; returns false when the bucket is full.
     */
    public function attempt(): bool
    {
        $store = $this->cache ?? Cache::store();
        $lockProvider = $store instanceof CacheRepository ? $store->getStore() : null;
        if (! $lockProvider instanceof LockProvider) {
            throw new LogicException('The cache store does not support atomic locks.');
        }
        $lock = $lockProvider->lock("leaky-bucket-lock:{$this->key}", 5);

        return $lock->block(5, function () use ($store): bool {
            $now = now()->getPreciseTimestamp(6) / 1_000_000;
            /** @var array{level: float, at: float}|null $state */
            $state = $store->get("leaky-bucket:{$this->key}");
            $level = 0.0;
            if ($state !== null) {
                $level = max(0.0, $state['level'] - ($now - $state['at']) * $this->leakPerSecond);
            }

            if ($level + 1 > $this->capacity) {
                $store->put("leaky-bucket:{$this->key}", ['level' => $level, 'at' => $now], 3600);

                return false;
            }

            $store->put("leaky-bucket:{$this->key}", ['level' => $level + 1, 'at' => $now], 3600);

            return true;
        });
    }
}
