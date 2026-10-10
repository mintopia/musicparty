<?php

namespace App\Support\Metrics;

class InMemoryCounterStore implements CounterStore
{
    /** @var array<string, int> */
    private array $counters = [];

    /** @var array<string, array<string, true>> */
    private array $sets = [];

    public function increment(string $key): void
    {
        $this->counters[$key] = ($this->counters[$key] ?? 0) + 1;
    }

    public function get(string $key): int
    {
        return $this->counters[$key] ?? 0;
    }

    public function many(array $keys): array
    {
        return array_map($this->get(...), $keys);
    }

    public function addMember(string $set, string|int $member): void
    {
        $this->sets[$set][(string) $member] = true;
    }

    public function members(string $set): array
    {
        return array_map(strval(...), array_keys($this->sets[$set] ?? []));
    }
}
