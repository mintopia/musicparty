<?php

namespace App\Services\Requests;

use App\Models\Party;
use Closure;
use Illuminate\Cache\Lock;
use Illuminate\Support\Facades\Cache;

class RequestLocks
{
    public const SECONDS = 30;

    /** @var array<int, Lock> */
    protected array $held = [];

    /**
     * Runs $callback holding the Party's request lock. Re-entrant only while this scope still owns the lock.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function run(Party $party, Closure $callback, ?int $waitSeconds = null): mixed
    {
        if ($this->holds($party)) {
            return $callback();
        }

        $lock = Cache::lock("add-request:{$party->id}", self::SECONDS);
        assert($lock instanceof Lock);
        $lock->block($waitSeconds ?? (int) config('musicparty.request_lock_wait_seconds'));
        $this->held[$party->id] = $lock;

        try {
            return $callback();
        } finally {
            unset($this->held[$party->id]);
            $lock->release();
        }
    }

    public function holds(Party $party): bool
    {
        $lock = $this->held[$party->id] ?? null;

        return $lock !== null && $lock->isOwnedByCurrentProcess();
    }
}
