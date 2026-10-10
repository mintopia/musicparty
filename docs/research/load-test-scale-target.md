# Load test against the scale target (T45)

## Question

Does one Reverb process with Redis scaling enabled hold 25 Live Parties x about 1,000 concurrent connections (design D11), and what should Horizon's worker counts be?

## Method

- Harness: `tests/Load/reverb-load.mjs` (Node 22 built-in `WebSocket`, no new dependencies). It opens `PARTIES x CONNS_PER_PARTY` Pusher-protocol connections subscribed to the public `party.{code}` channel, then publishes signed events through Reverb's HTTP API at `RATE` events per party per second (default 1, the coalesced Queue broadcast ceiling from D6) with a 4 KB payload. Latency is publish timestamp to client receipt on the same host.
- Matrix: `tests/Load/run-matrix.sh <dir>` starts a fresh `php artisan reverb:start` per scenario, 20 s per run, 2 repetitions at 250, 500, 750 and 1,000 connections.
- Rig: single 16-core Debian host, PHP 8.4, local Redis, `REVERB_SCALING_ENABLED=true`. Reverb, the harness and Redis share the host, so latencies exclude real network cost.

## Results

With `PULSE_ENABLED=false` and `TELESCOPE_ENABLED=false`:

| Connections | Loss | p50 | p95 | p99 | Deliveries/s |
|---|---|---|---|---|---|
| 250 | 0% | 12 ms | 21-22 ms | 24-27 ms | 238 |
| 500 | 0% | 13 ms | 28-41 ms | 39-46 ms | 475 |
| 750 | 0% | 16 ms | 27-29 ms | 30-33 ms | 713 |
| 1,000 | 0% on 1 of 2 runs (p95 38 ms, 950/s); the other run published nothing | 16 ms | 38 ms | 50 ms | 950 |

Reverb with Redis scaling is comfortably inside budget up to 750 connections.

## Findings

1. **Pulse and Telescope ingest stalled Reverb.** With both enabled, Reverb froze for 12-15 s at a time (p95 12-15 s, up to 8% loss at 750 connections). `REVERB_PULSE_INGEST_INTERVAL` and `REVERB_TELESCOPE_INGEST_INTERVAL` default to 15 s. In this rig the database host did not resolve, which is the likely trigger, but any slow database blocks the single event loop for the same reason. Not confirmed against a healthy database. Recommendation: disable both Reverb ingests on the production Reverb process, or confirm they are non-blocking.
2. **About 1,000 connections is not safe on the default event loop.** In most runs at exactly 1,000 connections the Reverb process died within seconds, and one run captured exit status 0 with nothing logged; one run in each matrix completed cleanly, and a short 5 s run also survived. Run counts were not tallied precisely. The PHP build here has no `ev`, `event` or `uv` extension, so ReactPHP falls back to `stream_select`, which is limited to 1,024 file descriptors. This is the probable cause but the threshold between 750 and 1,000 was **not** bisected and the extension was **not** tried. Recommendation: install `ext-ev` (or `ext-uv`) in the Reverb image and re-run the 1,000 level before relying on the target; until then, plan for about 750 connections per Reverb instance and scale out with the existing Redis option.
3. Redis scaling had no measurable cost: 750 connections gave 0% loss and p95 about 28 ms with scaling on, and the same shape with it off.

## Horizon tuning (not measured)

This task did not benchmark Horizon. The load test showed no queue pressure, because the harness published straight to Reverb. Recommendation, to be validated:

- Follow D11: split `player`, `polling`, `broadcast`, `mods-ai` and `default` into separate supervisors. `config/horizon.php` still has one supervisor over `default`, `partyupdates`, `mods-ai`, with `maxProcesses` 10 in production.
- `BroadcastPartyQueue` is unique per party with a 1 s delay, so at 25 parties the ceiling is 25 jobs/s. Starting point: `broadcast` 4 processes, `player` 3, `polling` 2, `mods-ai` 1-2, `default` 2.
- Follow-up: measure job latency with the real `BroadcastPartyQueue` against a seeded database at 25 parties before fixing these numbers.

## Open items

- Bisect the connection ceiling and test with an event-loop extension (finding 2).
- Re-test Pulse and Telescope ingest against a reachable database (finding 1).
- Horizon worker counts need a measured run (above).
