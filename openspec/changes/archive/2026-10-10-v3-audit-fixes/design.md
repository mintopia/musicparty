# Design

## Context

See proposal.md for the motivation. Finding numbers (#1 to #31) refer to the consolidated audit list. The full evidence for each is in the three audit reports (domain, playback, frontend-infra) that the decisions brief references. The constraints:

- These decisions stand and are not restated here: ADR-0001 to ADR-0007, plus the four new ADRs for this change: ADR-0008 (browser Reverb settings at runtime), ADR-0009 (Prometheus via `spatie/laravel-prometheus`, including Live Stats), ADR-0010 (leaky-bucket search limit) and ADR-0011 (remove all v1/v2 code).
- Octane and Horizon keep workers alive, so nothing request- or job-scoped may live in a singleton or a static.
- Scale target: 25 Live Parties, 500 Members per Party, about 1,000 realtime connections.
- Removal comes first. Later fixes touch the same files (`Party.php`, `RateNowPlaying`, `PartyController`, `Kernel.php`, the jobs), and they are much smaller once the dead code is gone.

## Goals / Non-Goals

**Goals:**

- No v1/v2 code, tables, packages or config remain. Every surviving feature has one v3 code path.
- A by-the-book deploy of the published image works: realtime connects, metrics are collected and protected, and the image is lean.
- Every audit finding except #4 is fixed, with a test that would have caught it.

**Non-Goals:**

- New product features. The only new behaviour is what the decisions require: metrics, the membership gate, rate limits and enqueue backoff.
- Changing the party code length, the Queue broadcast payload shape (apart from removing `sequence`) or the Stats page UI.
- A v2 data migration. Legacy tables are dropped, not converted (ADR-0011).

## Decisions

### D1. Prefactor, then delete (#8, #13)

Three v3 features currently lean on legacy code, so they are fixed before anything is deleted:

- `SelectPartyPlaylists` and `PartyPlaylistSelectionResource` switch from `backup_playlist_id` to `fallback_playlist_id`, the column that `TopUpFallbackRequests`, `FallbackPlaylistGate`, `ManagesBlocklist` and `UpdatePartySettings` already use. `backup_playlist_name` is dropped. The UI shows the playlist name by looking it up from the Provider.
- A new `Music\Listeners\AppendStartedTrackToHistory`, listening for `Queue\Events\TrackStarted` (dispatched after commit by `AdvanceQueue`), queues `AppendToHistoryPlaylist` when `history_playlist_id` is set. A failure writes a `playlist.history_append_failed` Party Log entry and never interrupts playback. It runs when a Track starts, not when it ends, so a Party that ends mid-Track still records it.
- `FallbackPlaylistGate::recentlyPlayedTrackIds()` reads `plays`, as `TopUpFallbackRequests` already does.
- Tests that rely on legacy models (`BannedMemberTest`, `PartyLifecycleTest`, `OpenApiTest`'s `/control` assertions, `ArchitectureRules` with `ArchitectureTest` and `CrossContextWriter`, and `LegacyRemovalTest`) are moved onto v3 models before the models go.

After that, deleting the legacy code (inventory in tasks group 2) must leave the suite green with no new skips.

### D2. Drop migrations (#8, #13, #14, #26)

New forward migrations drop, in foreign-key order: `song_ratings`, `votes`, `artist_song`, `played_songs`, `upcoming_songs`, `parties.song_id`, `songs`, `albums`, `artists`, `party_moderations`, `themes`, `websockets_statistics_entries`, `play_ratings` and `party_logs`. They also drop the legacy columns: on `parties`, `song_started_at`, `recent_device_id`, `device_id`, `device_name`, `queue`, `force`, `poll`, `last_updated_at`, `weighted`, `trustscore`, `trusted_user_id`, `show_qrcode`, `active`, `backup_playlist_id` and `backup_playlist_name`; `party_members.trustscore`; and on `users`, `status`, `status_updated_at` and `market`. `parties.downvotes` and `parties.downvotes_per_hour` stay because v3 reads them. Each migration's `down()` recreates the schema without data. A data migration deletes the `spotifysearch` social provider and its linked accounts. Every column is checked with grep immediately before it is dropped.

### D3. Runtime Reverb client config (#5, #28, #29; ADR-0008)

- `config/broadcasting.php` gains `connections.reverb.client` with `key`, `host`, `port` and `scheme`. They are read from `REVERB_APP_KEY` and `REVERB_PUBLIC_HOST/PORT/SCHEME`, and default to the parts of `APP_URL`: port 443 for https and 80 for http when `APP_URL` has none.
- `app.blade.php` builds `window.pusherConfig` from that key only. `bootstrap.js` creates `window.Echo` only when `appKey` is set, and otherwise sets a no-op stub so pages can call `Echo.channel()` safely.
- Compose (dev and example) runs `reverb:start --host=0.0.0.0` with no `--port`, so Reverb listens on `REVERB_SERVER_PORT` (default 8080), the port `REVERB_PORT` publishes to. `--debug` is removed from the example. Dev keeps it behind a commented flag.
- `production.env` and `example/.env.example` drop `VITE_REVERB_*` and set the internal `REVERB_HOST=reverb`, `REVERB_PORT=8080` and `REVERB_SCHEME=http`.

### D4. Minimal `.env.example` (#29)

`.env.example` keeps only what a fresh install must set: `APP_KEY`, `APP_URL`, the DB and Redis connection, the Reverb app id, key and secret, and the Spotify client id and secret. Everything else gets a working default in `config/*.php` (`BROADCAST_DRIVER` defaults to `reverb`, `QUEUE_CONNECTION` to `redis`). The `pusher` and `ably` broadcast connections and the `PUSHER_*`/`VITE_PUSHER_*` variables are deleted. CI's `cp .env.example .env` must still produce a booting test environment, so the test DB settings stay in `phpunit.xml` and the CI env.

### D5. Prometheus (#27; ADR-0009)

- `MetricsCollector` is registered as global HTTP middleware. It counts into Redis keys `metrics.http.requests`, `metrics.http.method.{METHOD}` and `metrics.http.status.{code}`, as it does now, and skips the metrics route itself. `PrometheusServiceProvider` exposes them as counters (`musicparty_http_requests_total`, `musicparty_http_requests_by_method_total{method}`, `musicparty_http_responses_by_status_total{code}`). Methods outside the standard set are counted as `OTHER` so label values stay bounded. `KEYS` is replaced with `SCAN`, or with fixed known label sets.
- The exception handler's `reportable` callback increments `metrics.exceptions`, which is exposed as `musicparty_uncaught_exceptions_total`. Validation, authorisation, 404 and other "don't report" exceptions are not counted.
- `registerHorizonCollectors()` is called during boot.
- Live Stats collectors read `party_stats.payload` for Parties that are Live or Paused:
  - `musicparty_parties{state}`, the number of Parties in each state.
  - `musicparty_party_members{party}`, Members excluding Banned ones.
  - `musicparty_party_queue_length{party}`, the number of Queued Requests.
  - `musicparty_party_time_played_seconds{party}`.
  - `musicparty_party_top_track_plays{party,rank,track}`.
  - `musicparty_party_top_requester_plays{party,rank,member}`.
  - `musicparty_party_most_upvoted_score{party,rank,track}`.
  - `musicparty_party_most_downvoted_score{party,rank,track}`.

  `track` is "Title — Artist, Artist". `member` is the nickname the Stats page shows. The prefix comes from `PROMETHEUS_NAMESPACE`, whose default changes from `app` to `musicparty`.
- Access: the package's `AllowIps` is replaced in `config/prometheus.php` middleware by `AuthorizeMetricsScrape`. It accepts `Authorization: Bearer <PROMETHEUS_TOKEN>`, compared in constant time, or a client IP inside `PROMETHEUS_ALLOWED_IPS` (CIDR, through `IpUtils`). It returns 403 otherwise, including when neither is configured.
- The image: the runtime stage copies `public/build` from the npm stage, not `/app` with `node_modules`. `.dockerignore` adds `tests`, `docs`, `openspec`, `asyncapi`, `example`, `.github` and `cookies.json`. The `openapi/` directory stays in the image if the `openapi:generate` command needs it at runtime.

### D6. Membership gate and limits (#12; ADR-0010)

- `PartyController::show` uses `Party::memberFor()`. A logged-in non-member is redirected to the join form with the code filled in. Joining stays an explicit `POST parties/join`. Ended Parties still allow joining for read-only history, as the membership spec requires.
- Named Laravel limiters in `RouteServiceProvider`:
  - `party-join`: 20 a minute per user, then per IP.
  - `party-requests`: 10 a minute per user.
  - `party-votes`: 60 a minute per user.

  They are applied to the web and API routes for the same action.
- Search uses `App\Support\RateLimiting\LeakyBucket` (a GCRA Lua script on the default Redis connection), keyed `search:{userId}`, with `musicparty.search_rate_limit.burst` (30) and `.per_second` (1). Over the limit, the API answers 429 with `Retry-After`. The web Party page renders with `searchError` and `retryAfter` props.

### D7. Locks and atomic claims (#6, #17, #22, #23, #24, #25)

- `PartyPlayers` is bound with `scoped()` (#6).
- `RequestTrack::place` re-reads the Party with `lockForUpdate()` and the Member with `fresh()` inside the transaction, and re-applies the state, `allow_requests`, Ban, `explicit` and `max_requests` checks against those rows (#17). `VoteOnRequest` refuses with `RequestRefusedException::partyEnded()` when the locked Party is Ended, as `RatePlay` already does (#17).
- Votes and Ratings lock the `PartyMember` row instead of the Party row. `RequestTrack`, `SelectUpNext` and `AdvanceQueue` keep the Party lock (#25). Measure before and after with `tests/Load/horizon-bench.sh` plus a Pest concurrency test of the downvote cap. If the Party lock shows no measurable contention at 300 voters, the task records the numbers in `docs/research/` and keeps the Party lock.
- `ProcessPlayerFrame` wraps its body in `Cache::lock("player-frame:{code}", 5)->block(3, ...)` (#22).
- `RunScheduledActions::claimIfDue` uses `Cache::add($key, $now, $everySeconds)` (#23). `BrowserPlayer::claim` uses `Cache::add` when there is no holder and `put` only when the same tab refreshes (#24).

### D8. Enqueue failure handling (#11, #21)

`PlaybackCoordinator::sendUpNext` catches any `Throwable` from `$player->enqueue()` and clears `enqueued_at` (#11). It then consults an enqueue backoff in cache, keyed by Request id: `playback.enqueue-backoff.{requestId}` holds `{attempt, next_at}`, and its schedule is `musicparty.playback.enqueue_backoff`, default `[5, 15, 30, 60, 120, 300]` seconds.

- Each failure writes a Party Log entry `player.enqueue_failed`, with details `{attempt, retry_in}`, and schedules the next attempt. Ticks before `next_at` skip the enqueue.
- When the last step fails, the system writes `player.enqueue_abandoned`, marks the backoff exhausted and stops trying for that Request. The Request stays Up Next with `enqueued_at` null.
- A Player connect, or a Host Player control (play, resume, skip), clears the backoff so the hand-off is tried again at once.
- `PlayerDisconnectedException` and broadcast or HTTP exceptions take the same path. Unexpected exceptions are also `report()`ed.

### D9. Stats off the request path (#7)

`RefreshStatsOnPartyActivity` dispatches a `RefreshPartyStatsJob` instead of computing inline. The job implements `ShouldBeUniqueUntilProcessing`, keyed by party id, with a 5-second delay, on the `default` queue. This follows the same pattern as `BroadcastPartyQueue`. `RefreshPartyStats` uses `upsert` on `party_id`, so concurrent runs cannot throw a unique-key error. Live Stats viewers see changes within about 5 to 10 seconds.

### D10. Client realtime pacing and resync (#15, #16)

- `Party/Show.vue` wraps its `QueueUpdatedEvent` handler in a trailing debounce of 500 ms plus random jitter of 0 to 1,500 ms, and reloads with `async: true` and `preserveState`.
- `bootstrap.js` dispatches a `realtime:resync` window event when the Pusher connection moves from `connecting`/`unavailable` to `connected` after the first connect, and on `visibilitychange` to visible. `Show.vue` reloads its props on it, and `Tv.vue` calls `router.reload`.
- The TV's `sequence <= lastSequence` guard, `PartyQueueSnapshot::nextSequence()` and its Redis counter are deleted, and `sequence` is removed from the payload and from `asyncapi.json`. `version` stays.

### D11. Smaller fixes

- `HostAccountTokens` calls `rejected()` only when the token endpoint returns `error=invalid_grant`. Any other 4xx raises `ProviderUnavailableException` (#10).
- `SpotifyMusicProvider::playlistTracks` is wrapped in `Cache::remember("music.spotify.playlist.{id}", 300, ...)`. Changing the Fallback Playlist setting or re-validating the gate forgets that key (#18).
- `Setting::$cached` and `clearCache`'s static path are removed. `fetch` uses the `Cache` layer only. `SettingObserver` keeps forgetting the cache key, and `AdminSiteConfigTest` loses its reflection reset (#19).
- Act-as-Host enter and leave call `Party\Actions\RecordPartyLogEntry` with `details: ['acting_as_host' => true]` (#26).

### D12. Horizon queues and CI (v3 1.6, v3 1.8, #20)

- Queues:
  - `player`: `ProcessPlayerFrame`, `StartPlayback`, `TickPlayback` and `CheckSoloistHealth`.
  - `polling`: `PollPlayback`.
  - `broadcast`: `BroadcastPartyQueue`, plus the queue for broadcast events that are not `ShouldBroadcastNow`.
  - `mods-ai`: `ReviewRequestWithAi`.
  - `default`: `RunModScheduledActions`, `RefreshPartyStatsJob`, `AppendToHistoryPlaylist` and anything else.

  `partyupdates` is removed. `config/horizon.php` gets one supervisor per queue group, using the worker counts from `docs/research/load-test-scale-target.md`. Each job's queue is asserted in tests.
- CI gains a `frontend` job (`npm ci`, `npm test`, `npm run build`) and a `specs` job that lints `openapi/openapi.json` and `asyncapi/asyncapi.json`. The linters are `@redocly/cli` and `@asyncapi/cli`, run with `npx` at pinned versions, so they are not added to `package.json`. If the user prefers to vendor them as dev dependencies, that needs no ADR because both are tools, not runtime packages.

## Task group ordering (drives ticket `blocked_by` edges)

| Group | Blocked by | Why |
|---|---|---|
| 1. Prefactor | none | Moves v3 code and tests off legacy symbols, so deletions cannot hide regressions |
| 2. Legacy removal | 1 | Deletes what group 1 freed. Every later group edits files this group shrinks |
| 3. Config, deployment and CI | 2 | Touches `Kernel.php`, Compose, `.env.example`, `composer.json` and job queues, which group 2 also edits |
| 4. Domain correctness | 2 | Edits `RequestTrack`, `VoteOnRequest`, `RatePlay` and `PartyController`, after `RateNowPlaying` and the legacy controllers are gone |
| 5. Playback and players | 2 and 3.5 | Edits the jobs whose queues 3.5 renames |
| 6. Realtime frontend | 3.1 | Edits `bootstrap.js` after the runtime config change |
| 7. Metrics | 2 (7.3 also by 4.5) | Edits `Kernel.php`, the exception handler and `config/prometheus.php` after group 2 cleans them up. 7.3 reads the `party_stats` that 4.5 keeps fresh |
| 8. Wrap-up | all | Full suite, `/no-comments`, spec sync |

Inside group 2, task 2.1 (secrets and open endpoints) should merge first, and 2.9 (drop migrations) last because it needs every model and reader gone. Inside group 4, 4.2 depends on 4.1 because both edit the same transaction blocks. Inside group 7, 7.2 and 7.3 depend on 7.1 (same provider). Groups 4, 5, 6 and 7 can otherwise run in parallel.

## Risks / Trade-offs

- **Dropping tables loses old v2 data.** → ADR-0011. The operator backs up first. Migrations are forward-only in practice: `down()` recreates the schema without data.
- **The member-row lock could let two votes break the per-Party invariants.** → Only the per-Member downvote cap and the Ban check need serialising, and both are per Member. Selection still takes the Party lock. Measure first, and keep the Party lock if contention is negligible.
- **Enqueue backoff could leave a Party silent after it gives up.** → The final Party Log entry shows up on the Moderator channel, and any Player reconnect or Host control retries at once.
- **Top-N labels in Prometheus turn over as rankings change.** → Five ranks and only Live or Paused Parties keep the bound small (ADR-0009).
- **The membership gate changes shared links.** → `/parties/{code}` now lands on a prefilled join form, one extra click. The TV URL is unaffected.
- **Removing Telescope removes a debugging tool.** → Pulse and Horizon remain. The load test found Telescope ingest stalled Reverb.

## Migration Plan

1. Merge groups 1 and 2 to `feature/v3-rewrite` behind a green CI.
2. Operator: back up the database, then deploy (the drop migrations run). Set `REVERB_PUBLIC_*` only if the public address is not `APP_URL`, and set `PROMETHEUS_TOKEN` or `PROMETHEUS_ALLOWED_IPS`.
3. Merge groups 3 to 7 as they finish. Each is deployable on its own.

Rollback means redeploying the previous image. Once the drop migrations have run, restoring the backup is the only way to get legacy data back.

## Open Questions

- Telescope: remove it (recommended, and planned in task 2.8) or keep it gated? The decisions brief recommends removal but leaves it open.
- Should the Prometheus top-N metrics include track titles and Member nicknames as labels (planned), or only rank and value?
- Enqueue backoff schedule: is `[5, 15, 30, 60, 120, 300]` seconds (about 9 minutes before giving up) acceptable?
- Search defaults: is a burst of 30 with a sustained 1 per second "generous" enough?
