# Vote and Rating lock benchmark (task 4.2, audit #25)

## Question

Does locking the Party row (`lockForUpdate`) in `VoteOnRequest` and `RatePlay` cause contention at 300 concurrent voters, and does locking the Member row instead fix it?

## Method

- Harness: `tests/Load/vote-bench.php`. It seeds one Live Party with 300 Members and 60 queued Requests plus one Playing Request with a Play, then forks one process per Member against MariaDB. Each process waits for a common start time, then runs 5 rounds of one random Up/Down `VoteOnRequest` followed by one random Up/Down `RatePlay`. Latency is wall time per action call.
- Run with `php tests/Load/vote-bench.php` (`VOTERS`, `ROUNDS`, `REQUESTS`, `CAP`, `WITH_STATS=1`).
- The `VoteCast` listener (`RefreshStatsOnPartyActivity`) is detached by default so the lock itself is measured; see finding 3.
- Rig: one 16-core Debian host, PHP 8.4, MariaDB 11.8 (InnoDB, REPEATABLE READ), 3 runs per variant. The 300 voters, the database and the harness share the host, so numbers are relative, not absolute.

## Results

300 voters, 1,500 votes and 1,500 ratings per run, 0 refusals, 0 errors.

| Variant | Votes p50 | Votes p95 | Votes p99 | Ratings p50 | Ratings p95 | Ratings p99 |
|---|---|---|---|---|---|---|
| Before: Party `FOR UPDATE` | 575-600 ms | 795-929 ms | 996-1,174 ms | 583-632 ms | 933-1,114 ms | 988-1,174 ms |
| After: Party `FOR SHARE` + Member `FOR UPDATE` | 151-177 ms | 539-714 ms | 674-844 ms | 125-134 ms | 242-315 ms | 261-377 ms |
| Reference: 20 voters, Party `FOR UPDATE` | 45-74 ms | 85-88 ms | 88 ms | 31-44 ms | 49-55 ms | 50-56 ms |

## Findings

1. **The Party lock is contended.** Median latency grows about 10x between 20 and 300 voters, because every vote and rating by every Member queues on one row.
2. **Locking the Member row removes most of it.** Median vote latency falls about 3.7x and median rating latency about 4.7x. Votes by different Members no longer wait on each other.
3. **Adjacent bottleneck, not changed here:** with the `VoteCast` listener attached, `RefreshPartyStats` rewrites the single `party_stats` row synchronously after every vote. At 300 voters this produced `Lock wait timeout exceeded` errors on `party_stats`, so the stats refresh limits vote throughput independently of the Party lock. Worth moving to a debounced job.

## Decision

Adopted. `VoteOnRequest` and `RatePlay` take a shared lock (`FOR SHARE`) on the Party row and an exclusive lock on the Member row. Request and queue advance keep the exclusive Party lock.

- The shared Party lock keeps the "no votes after a Party ends" guarantee: `EndParty` takes the exclusive lock, so it waits for in-flight votes and later votes see the Ended state. Shared locks do not block each other.
- Lock order is Party then Member everywhere, so there is no new deadlock cycle.
- The per-hour downvote cap is per Member, so serialising one Member's votes is enough. `tests/Feature/Queue/DownvoteCapConcurrencyTest.php` casts 10 parallel downvotes by one Member in separate processes and asserts exactly the cap is accepted. With the Member lock removed that test fails in most runs.
