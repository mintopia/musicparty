<?php

use App\Support\RateLimiting\LeakyBucket;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

beforeEach(function () {
    try {
        Redis::connection()->ping();
    } catch (Throwable) {
        $this->markTestSkipped('Redis is not reachable.');
    }

    $this->key = 'test:leaky-bucket:'.Str::uuid();
    $this->bucket = app(LeakyBucket::class);
    Carbon::setTestNow(Carbon::parse('2026-01-01 12:00:00'));
});

afterEach(function () {
    if (isset($this->bucket)) {
        Redis::connection()->del($this->key);
    }
    Carbon::setTestNow();
});

it('admits the burst, refuses the next attempt, and refills at the configured rate in Redis', function () {
    foreach (range(1, 30) as $attempt) {
        expect($this->bucket->attempt($this->key, 30, 1.0))->toBe(0);
    }

    expect($this->bucket->attempt($this->key, 30, 1.0))->toBe(1000);

    Carbon::setTestNow(Carbon::now()->addSecond());

    expect($this->bucket->attempt($this->key, 30, 1.0))->toBe(0)
        ->and($this->bucket->attempt($this->key, 30, 1.0))->toBe(1000);
});
