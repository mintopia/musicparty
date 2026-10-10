# Load test against the scale target (T45)

## Question

Does Reverb with Redis scaling hold 25 Live Parties x 500 Members (about 1,000 concurrent connections, design D11), and what should Horizon's worker counts be?

## Method

- Harness: `tests/Load/reverb-load.mjs` (Node 22 built-in `WebSocket`, no new dependencies). It opens `PARTIES x CONNS_PER_PARTY` Pusher-protocol connections subscribed to the public `party.{code}` channel, then publishes signed events through Reverb's HTTP API at `RATE` events per party per second (default 1, the coalesced Queue broadcast ceiling from D6) with a 4 KB payload. Latency is publish timestamp to client receipt on the same host.
- Matrix: `tests/Load/run-matrix.sh <dir>` starts a fresh `php artisan reverb:start` per scenario, 20 s per run, 2 repetitions per level. `REVERB_PORTS=8111,8112` splits connections across several Reverb instances.
- Rig: single 16-core Debian host, PHP 8.4, local Redis, `REVERB_SCALING_ENABLED=true`. Reverb, the harness and Redis share the host, so latencies exclude real network cost.

## Results

With `PULSE_ENABLED=false` and `TELESCOPE_ENABLED=false`, one Reverb process, Redis scaling on:

| Connections | Loss | p50 | p95 | p99 | Deliveries/s |
|---|---|---|---|---|---|
| 250 | 0% | 12 ms | 21-22 ms | 24-27 ms | 238 |
| 500 | 0% | 13 ms | 28-41 ms | 39-46 ms | 475 |
| 750 | 0% | 16 ms | 27-29 ms | 30-33 ms | 713 |
| 900 | 0% | 15 ms | 28 ms | 34 ms | 810 |
| 950 | 0% | 16 ms | 26 ms | 30 ms | 855 |
| 1,000 | Reverb exits (status 0) before any event is delivered | - | - | - | - |
| 1,025 | 15 of 1,025 connections refused, server exits | - | - | - | - |
| 1,000 across **2** Reverb instances (500 each, shared Redis) | 0% | 15 ms | 41 ms | 51 ms | 933 |

## Findings

1. **A single Reverb process tops out just under 1,000 connections.** 950 is clean, 1,000 is not: the process exits with status 0 and no log output, reproduced in every 1,000-connection run in the bisect. The PHP build has no `ev`, `event` or `uv` extension, so ReactPHP uses `stream_select`, which cannot watch more than 1,024 file descriptors (the clients, the listener, Redis and each publish request all count). The fd limit is the explanation that fits; installing an event-loop extension was not tried.
2. **The 1,000-connection target is met by the design's own scale-out path.** Two Reverb instances sharing Redis (`REVERB_SCALING_ENABLED=true`) carried 1,000 connections with 0% loss and p95 41 ms, publishing to one instance and delivering through both. Run at least two Reverb processes for the target, or add `ext-ev` and re-test a single instance.
3. **Pulse and Telescope ingest stalled Reverb.** With both enabled, Reverb froze for 12-15 s at a time (p95 12-15 s, up to 8% loss at 750 connections). `REVERB_PULSE_INGEST_INTERVAL` and `REVERB_TELESCOPE_INGEST_INTERVAL` default to 15 s. In this rig the database host did not resolve, which is the likely trigger; any slow database blocks the single event loop the same way. Not checked against a healthy database. Disable both on the Reverb process.
4. **Redis scaling has no measurable cost** at these loads.

## Horizon tuning

Benchmark (`tests/Load/horizon-bench.sh`): 2,000 `QueueUpdatedEvent` broadcast jobs (4 KB payload, 25 parties) queued on Redis, drained into a live Reverb with N `queue:work` processes. Times include worker boot.

| Workers | Seconds | Jobs/s |
|---|---|---|
| 1 | 6.1 | 327 |
| 2 | 4.8 | 419 |
| 4 | 4.1 | 491 |
| 8 | 4.0 | 506 |

Throughput levels off at about 500 jobs/s because the single Reverb loop becomes the limit. The demand ceiling is 25 jobs/s: `BroadcastPartyQueue` is unique per party with a 1 s delay, so 25 parties produce at most 25 jobs/s. One worker has more than 10x headroom.

Applied in `config/horizon.php`:

- `supervisor-broadcast` (`broadcast`): production `maxProcesses` 10 -> 4. Four workers cover the measured plateau with room for bursts; more only add load on Reverb.
- New `supervisor-ai` (`mods-ai`, `simple` balance): production 2, local 1. Slow AI review no longer shares a worker pool with broadcasts, so it cannot starve them (D11).
- `player`, `polling` and `default` each have their own supervisor too (production 4, 2 and 2; local 1).

## Caveats

- Everything ran on one host (16 cores) with Reverb, Redis, the harness and the workers competing for it; there is no real network cost in the latencies.
- The Horizon benchmark drives the broadcast job only, not `BroadcastPartyQueue` against a database, so database time per job is not included.
- Pulse and Telescope were disabled for the Reverb runs; finding 3 is the reason.
