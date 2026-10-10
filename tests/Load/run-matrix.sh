#!/usr/bin/env bash
# Usage: tests/Load/run-matrix.sh <output-dir>; runs Reverb from a fresh process per scenario.
set -u
out="${1:?output dir}"; mkdir -p "$out"
start_reverb() {
    for p in $(pgrep -f "^php .*artisan reverb:start"); do kill -9 "$p"; done; sleep 1
    nohup php artisan reverb:start --host=127.0.0.1 --port=8080 > "$out/reverb.log" 2>&1 &
    sleep 5
}
for conns in 10 20 30 40; do
    for rep in 1 2; do
        start_reverb
        PARTIES=25 CONNS_PER_PARTY=$conns SECONDS=20 node tests/Load/reverb-load.mjs > "$out/c$((conns*25))-r$rep.json" 2>/dev/null
    done
done
for p in $(pgrep -f "^php .*artisan reverb:start"); do kill -9 "$p"; done
