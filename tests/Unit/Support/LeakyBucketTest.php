<?php

use App\Support\LeakyBucket;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-10-13 12:00:00');
    $this->cache = new Repository(new ArrayStore);
});

afterEach(fn () => Carbon::setTestNow());

function bucket(Repository $cache, string $key = 'k', int $capacity = 3, float $leak = 1.0): LeakyBucket
{
    return new LeakyBucket($key, $capacity, $leak, $cache);
}

it('allows a burst up to capacity and then refuses', function () {
    $bucket = bucket($this->cache);

    expect([$bucket->attempt(), $bucket->attempt(), $bucket->attempt(), $bucket->attempt()])->toBe([true, true, true, false]);
});

it('leaks over time so capacity returns', function () {
    $bucket = bucket($this->cache, capacity: 2, leak: 0.5);
    $bucket->attempt();
    $bucket->attempt();
    expect($bucket->attempt())->toBeFalse();

    Carbon::setTestNow(now()->addSeconds(2));
    expect($bucket->attempt())->toBeTrue()->and($bucket->attempt())->toBeFalse();
});

it('does not leak below empty', function () {
    $bucket = bucket($this->cache, capacity: 2);
    $bucket->attempt();

    Carbon::setTestNow(now()->addHour());

    expect([$bucket->attempt(), $bucket->attempt(), $bucket->attempt()])->toBe([true, true, false]);
});

it('keeps separate buckets per key', function () {
    $one = bucket($this->cache, 'one', 1);
    $two = bucket($this->cache, 'two', 1);
    $one->attempt();

    expect($one->attempt())->toBeFalse()->and($two->attempt())->toBeTrue();
});

it('shares state between instances with the same key', function () {
    bucket($this->cache, 'shared', 1)->attempt();

    expect(bucket($this->cache, 'shared', 1)->attempt())->toBeFalse();
});
