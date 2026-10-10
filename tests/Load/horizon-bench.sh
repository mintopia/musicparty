#!/usr/bin/env bash
# Usage: tests/Load/horizon-bench.sh <reverb-port> [jobs]; drains queued QueueUpdatedEvent broadcast jobs with 1, 2, 4 and 8 workers.
set -u
port="${1:?reverb port}"; jobs="${2:-2000}"
export REDIS_DB=7 QUEUE_CONNECTION=redis BROADCAST_CONNECTION=reverb REVERB_PORT="$port" REVERB_HOST=127.0.0.1 REVERB_SCHEME=http
for workers in 1 2 4 8; do
    php artisan tinker --execute="Illuminate\Support\Facades\Redis::connection()->flushdb(); for (\$i = 0; \$i < $jobs; \$i++) { App\Events\Party\QueueUpdatedEvent::dispatch('LOAD'.(\$i % 25), ['queue' => str_repeat('x', 4000)]); } echo 'queued '.Illuminate\Support\Facades\Queue::size();" >/dev/null 2>&1
    start=$(date +%s.%N)
    for w in $(seq 1 "$workers"); do php artisan queue:work redis --stop-when-empty --queue=default --tries=1 --quiet >/dev/null 2>&1 & done
    wait
    end=$(date +%s.%N)
    awk -v w="$workers" -v j="$jobs" -v s="$start" -v e="$end" 'BEGIN { printf "workers=%d jobs=%d seconds=%.1f jobs_per_sec=%.0f\n", w, j, e - s, j / (e - s) }'
done
