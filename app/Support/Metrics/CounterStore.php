<?php

namespace App\Support\Metrics;

interface CounterStore
{
    public function increment(string $key): void;

    public function get(string $key): int;

    /**
     * @param  list<string>  $keys
     * @return list<int>
     */
    public function many(array $keys): array;

    public function addMember(string $set, string|int $member): void;

    /**
     * @return list<string>
     */
    public function members(string $set): array;
}
