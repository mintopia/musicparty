<?php

namespace App\Support\Metrics;

use Illuminate\Support\Facades\Redis;

class RedisCounterStore implements CounterStore
{
    public function increment(string $key): void
    {
        Redis::incr($key);
    }

    public function get(string $key): int
    {
        return (int) Redis::get($key);
    }

    public function many(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $values = Redis::mget($keys);

        return array_map(fn (int $index): int => (int) ($values[$index] ?? 0), array_keys($keys));
    }

    public function addMember(string $set, string|int $member): void
    {
        Redis::sadd($set, $member);
    }

    public function members(string $set): array
    {
        return array_map(strval(...), array_values(Redis::smembers($set)));
    }
}
