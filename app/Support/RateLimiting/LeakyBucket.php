<?php

namespace App\Support\RateLimiting;

use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Support\Carbon;

class LeakyBucket implements Bucket
{
    private const string SCRIPT = <<<'LUA'
local now = tonumber(ARGV[1])
local interval = tonumber(ARGV[2])
local tolerance = tonumber(ARGV[3])
local tat = tonumber(redis.call('GET', KEYS[1]))
if tat == nil or tat < now then
    tat = now
end
local allowAt = tat - tolerance
if now < allowAt then
    return allowAt - now
end
local newTat = tat + interval
redis.call('SET', KEYS[1], newTat, 'PX', math.ceil(newTat - now) + 1000)
return 0
LUA;

    public function __construct(private readonly RedisFactory $redis) {}

    /**
     * @return int Milliseconds until the next attempt is allowed; 0 when this attempt was admitted.
     */
    public function attempt(string $key, int $burst, float $perSecond): int
    {
        $interval = 1000 / $perSecond;
        $tolerance = $interval * ($burst - 1);
        $now = Carbon::now()->getTimestampMs();

        $wait = $this->redis->connection()->command('eval', [self::SCRIPT, [$key, $now, $interval, $tolerance], 1]);

        return (int) ceil((float) $wait);
    }
}
