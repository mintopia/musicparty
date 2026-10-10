# Design

## Context

See proposal.md for the motivation. Finding ids (`code-H1`, `arch-3`, `devops-M4` and so on) refer to the 2026-10-10 v3 review: `code-review.md`, `arch-review.md` and `devops-review.md`, with the user's decision on each finding. Where the user left a note, the note is the decision and overrides the report's suggested fix. The constraints:

- These decisions stand and are not restated here: ADR-0001 to ADR-0011, plus the thirteen new ADRs for this change, ADR-0012 to ADR-0024. ADR-0015 supersedes ADR-0002; ADR-0014 amends ADR-0005.
- Tests run on in-memory SQLite and need nothing else, locally and in CI (ADR-0024). Every migration and query must work on SQLite and MariaDB.
- Migrations must upgrade a v2 database as well as install fresh. Losing v2 data is acceptable.
- Octane and Horizon keep workers alive, so nothing request- or job-scoped may live in a singleton or a static.
- Scale target (unchanged): 25 Live Parties, 500 Members per Party.
- Settled earlier and not reopened: the `swordfish` `REVERB_APP_SECRET` placeholder, app-to-Reverb traffic on the internal Docker network with browsers using the public settings (ADR-0008), `.env.example` holding only the minimum, and the baked production defaults in `production.env` (devops-M8, won't fix).
- The prefactor comes first. Moving every model, event and job into its context (ADR-0012) touches almost every file the later groups edit, so it lands before them, and it needs a green suite on SQLite to land safely.

## Goals / Non-Goals

**Goals:**

- Every review finding except devops-M8 is fixed, each with a test at one of the seams below that would have caught it.
- A developer can run the whole suite with only PHP and SQLite, and cannot wipe their own database by doing so.
- Revoking access (Ban, role removal, suspension, Player Token revocation) cuts off live realtime access within seconds.
- A Queue change costs one broadcast, not one HTTP request per Member.
- An Operator can deploy the published image by following the README, with unprivileged containers, ordered start-up, automatic migrations and persistent Redis.
- Every HTTP endpoint, API Resource, channel and broadcast event is documented, and CI fails otherwise.

**Non-Goals:**

- New product features beyond what the decisions require.
- Changing the party code length, making the TV screen or the public channel authenticated (accepted risk, ADR-0019), or restricting Reverb origins by default.
- Preserving v2 data through the upgrade.
- Shipping resource limits, backup jobs or a reverse-proxy stack. These are documented, not implemented.
- A MariaDB or Redis job in CI.

## Decisions

### D1. Tests on SQLite with nothing else (code-H1, ADR-0024)

`phpunit.xml` sets the SQLite in-memory connection and array/sync stores. `Tests\TestCase::setUp` checks `config('database.default') === 'sqlite'` and `config('database.connections.sqlite.database') === ':memory:'` and throws otherwise, before `RefreshDatabase` runs. Two tests use Redis today. `MetricsTest` is fixed by putting the counters behind a `CounterStore` interface (Redis implementation in production, in-memory in tests). `LeakyBucketRedisTest` exercises the Lua script itself, which only Redis can run, so it moves to an opt-in `redis` group, excluded from the default run and from CI. `FakeLeakyBucket` keeps covering callers. CI's test job loses its MariaDB and Redis services.

### D2. One portable v3 baseline migration (arch-14)

The 79 migrations include the whole v1/v2 create-then-drop chain and an invalid timestamp. They are replaced by one baseline migration that builds the v3 schema with the schema builder only (no raw SQL, no `schema:dump`, which produces a MariaDB-specific dump SQLite cannot load). Newer migrations (1.3, 1.4, 3.1 and later) follow it as ordinary migrations.

The baseline must cope with three starting points:

| Starting database | Detected by | Baseline does |
|---|---|---|
| Empty | no application tables | creates the v3 schema |
| v2, or a partial v3 | application tables exist, final pre-squash v3 migration (`2026_10_10_020002_delete_spotifysearch_social_provider`) not recorded | drops every application table that exists, then creates the v3 schema |
| v3 before the squash | final pre-squash v3 migration recorded | records itself only |

The drop list covers every table any past migration created: `websockets_statistics_entries`, `artists`, `albums`, `songs`, `artist_song`, `played_songs`, `upcoming_songs`, `votes`, `song_ratings`, `themes`, `party_moderations`, `mods`, `mod_settings`, `party_mod_settings`, `party_mod_setting_events`, `party_logs`, `play_ratings`, `integration_tokens`, plus the v3 tables. Pulse keeps its published migration. Social providers are re-seeded by `SeedSocialProviders` after a v2 upgrade; the admin re-enters provider credentials (operator step). Rows for deleted migration files in `migrations` are left alone; Laravel ignores them.

The migration test seam builds the v2 schema from a fixture class (`tests/Fixtures/Schema/V2Schema`, schema-builder code mirroring the final v2 tables) and the pre-squash v3 schema from the current migrations captured as another fixture, then runs `migrate` and asserts the result.

### D3. One active Track per Party in the database (arch-14)

MariaDB has no partial unique indexes, so the invariant uses generated columns, which both MariaDB and SQLite support through Laravel's `virtualAs`/`storedAs`:

- `up_next_party_id` = `party_id` when `status = 'up_next'`, else null, with a unique index.
- `playing_party_id` = `party_id` when `status = 'playing'`, else null, with a unique index.

Unique indexes allow many nulls on both drivers. Row locks stay as the first line; the constraint catches anything that slips past them.

### D4. Contexts own their classes (arch-adr-1, arch-4, ADR-0012)

Model placement:

| Context | Models |
|---|---|
| Identity | `User`, `LinkedAccount`, `SocialProvider` |
| Membership (new) | `PartyMember` (plus `PartyRole`, joining, Bans, role changes moved from Party) |
| Party | `Party`, `PartyLogEntry`, `BlocklistEntry` |
| Queue | `TrackRequest`, `RequestVote`, `Play`, `Rating` |
| Playback | none (Player Tokens are Sanctum tokens on `Party`) |
| Music | none (`LinkedAccount` tokens are read through Identity) |
| Mod | `PartyMod` |
| Stats | `PartyStat` |
| Theming | `InstanceTheme` |
| Admin | `Setting`, `ProviderSetting`, `Role`, `AdminAuditEntry`, `AdminHostSession`, `Integration` (new, 3.1) |

Broadcast events go to `<Context>/Broadcast`, jobs to `<Context>/Jobs`, listeners to `<Context>/Listeners`: `QueueUpdatedEvent`, `PendingRequest*`, `RequestDecided`, `RequestRejected` and `BroadcastPartyQueue` to Queue; `PartyLogEntryAddedEvent` to Party; `StatsUpdatedEvent` and `RefreshPartyStatsJob` to Stats; `ThemeUpdatedEvent` to Theming; the Player command events, `ProcessPlayerFrame`, `StartPlayback`, `TickPlayback` and `HandlePlayerClientEvent` to Playback; `ReviewRequestWithAi` and `RunModScheduledActions` to Mod. `app/Services/SocialProviders` goes to Identity. `AsyncApiCoverage` goes to a small `Realtime` support namespace beside the new `RealtimeConnections` contract (D7). `MessageLoggedListener` is deleted (code-L5).

A morph map with the current short aliases keeps polymorphic columns stable. The architecture test (2.2) detects writes by type, not by matching `Short::create(`.

### D5. Music Provider seam (arch-6)

`MusicProvider` keeps catalogue concerns only: search, track lookup, playlists, history append, and a new `forgetPlaylist()` for cache invalidation. Player operations (`currentPlayback`, `queueTrack`) move to a Playback-owned `SpotifyPlaybackClient`, which the Polling Player uses. Provider resolution happens one way, by the Party's provider id. `PartyPlayers::register` uses a `PlayerFactory` keyed by Player kind, and the Fake Player is registered only by the test service provider. Spotify-only token handling moves under `Music\Providers\Spotify`.

### D6. Broadcast inventory and wire names (arch-2, arch-15)

Every broadcast gets an explicit dotted wire name. Expected outcome of the audit in 4.4 (the implementer confirms each consumer):

| Event (wire name) | Channel | Consumer after this change |
|---|---|---|
| `queue.updated` | `party.{code}` | `Show.vue`, `Tv.vue` apply the payload |
| `party.state_changed` (new broadcast of `PartyStateChanged`) | `party.{code}` | `Show.vue`, `Tv.vue` |
| `theme.updated` | `party.{code}` | `Tv.vue`, `Show.vue` |
| `stats.updated` | `party.{code}` | `Stats.vue` |
| presence | `party.{code}.members` | `Members.vue`; joined by every authenticated Party page (D7) |
| `pending_request.added`, `pending_request.resolved` | `party.{code}.moderators` | `Pending.vue` |
| `party_log.entry_added` | `party.{code}.moderators` | `Log.vue` |
| `request.rejected`, `request.decided` | `party.{code}.member.{id}` | toast on `Show.vue` |
| `member.vote_changed`, `member.rating_changed` (new) | `party.{code}.member.{id}` | `Show.vue` merges own state |
| `member.banned` (new) | `party.{code}.member.{id}` | `Show.vue` shows the Ban, then the connection is terminated |
| `browser-player.command` | `party.{code}.browser-player` | Browser Player |
| `player.command` | `player.{code}` | Soloist |

If `request.decided` and `pending_request.resolved` turn out to carry the same information for the same audience, one is deleted. Any event with no consumer after 4.4 is deleted with its AsyncAPI entry (YAGNI), and the realtime spec delta is amended in the same PR.

### D7. Cutting off realtime access (arch-1, arch-adr-9, ADR-0019)

Reverb authorises a channel once. Two mechanisms close the gap:

- **Members.** Reverb's Pusher HTTP API has a terminate-user-connections endpoint that disconnects every connection whose presence subscription carries that user id. Every authenticated Party page joins the Party's presence channel, so each Member connection carries the user id. A `RealtimeConnections` contract (`terminateUser(int $userId)`) wraps the call, with a fake for tests. `BanMember`, `ChangeMemberRole` on demotion, `SuspendUser` and `RevokeRole` call it after commit. The client's Echo reconnects and re-authorises each channel, which now fails where access was removed. Resync (ADR-0018) refetches state on reconnect.
- **Players.** A Player connection has no user id. When `player.{code}` is authorised, the app stores `socket_id → personal_access_token_id` in the cache (TTL of a day, refreshed on use). `HandlePlayerClientEvent` runs inside Reverb with the connection object; it looks up the token for the socket, and if the token is revoked, expired or unknown it drops the frame and calls `$connection->disconnect()`. `RevokePlayerToken` also clears the Party's "command target" so `PlayerCommandEvent` is not sent while no valid token is connected. Soloist sends position frames every few seconds, so revocation takes effect within that interval.

The public channel and TV screen stay unauthenticated by design (ADR-0019), with a per-address limiter on the code-addressed web routes (code-L9).

### D8. One serializer and one Score (arch-3, code-M8, ADR-0018)

`QueueEntryPresenter` produces the public fields of a Queue entry: Request id, Track fields, requester nickname, `score`, status, `started_at`, Decorations. `QueueEntryResource` (API and Inertia props) and `PartyQueueSnapshot` (broadcast) both call it, and the Queue order comes from one query object shared with `SelectUpNext`'s eligibility ordering. Member-specific fields (`my_vote`, `my_rating`) are not in public payloads; the API and page props add them for the current Member, and member channel events update them.

`score` is the Score as the glossary defines it: Vote sum plus Score Modifier adjustments, the value selection uses. The user's note asked for the weighting to be reflected in the displayed order and Score, and plain Scores in "lottery/raffle mode" even when the top Track may not play next. The code has two selection modes: deterministic, and weighted (random in proportion to Score, which is the raffle). The design reads "weighting" as the Score Modifier adjustments, which are now always included, and "lottery/raffle mode" as weighted selection, where the list shows Scores in order with a note that the next Track is drawn at random. This reading needs confirming (see Open Questions).

### D9. Per-Party ticks (code-M5, arch-7, ADR-0021)

`TickPlayback` and `RunModScheduledActions` become dispatchers. Each Live Party gets its own `TickParty` (and `RunPartyScheduledActions`) job:

- `ShouldBeUnique` per Party, so a backlog cannot stack ticks for one Party.
- `WithoutOverlapping("tick:{party}")->expireAfter(30)`, so a killed job frees the Party within 30 seconds.
- 20-second job timeout and a 5-second HTTP timeout on Spotify calls.
- A new `ticks` queue with its own supervisor, so ticks do not wait behind Player frames on `player`.

A tick reads state from the database and only calls the Provider when it has a hand-off to make (enqueue) or a playlist read whose cache has expired. Playback observation comes from the Player: Soloist over Reverb, the Browser Player over HTTP, and the Polling Player's own `PollPlayback` job on its own interval. A test asserts zero Provider calls over 60 idle ticks, so the Spotify rate limit is not spent on ticking.

Enqueue outcomes are classified (code-M6): "not received" (Player disconnected, connection refused, 4xx other than 429) clears the claim and backs off; "unknown" (timeout, 5xx) keeps the claim as unconfirmed until the Player's next reported state either shows the Track queued or playing (confirmed) or not (released for retry).

### D10. Image and entrypoint (devops-H2, devops-M2, devops-M3, devops-L3, devops-L5, ADR-0022, ADR-0023)

- Build stages copy `composer.json`/`composer.lock` and `package.json`/`package-lock.json` first and install, then copy source. `npm ci`, no `--ignore-platform-reqs`.
- The entrypoint (`/usr/local/bin/musicparty`) runs as root only for set-up: create or modify the `musicparty` user and group to `PUID`/`PGID`, `chown -R` `storage` and `bootstrap/cache` only, `php artisan optimize`, then `exec su-exec musicparty` the role. Roles map to commands: `web` → `octane:frankenphp --port=8080 --caddyfile=/Caddyfile`, `horizon`, `scheduler` → `schedule:work`, `reverb` → `reverb:start --host=0.0.0.0`, `migrate` → `migrate --force --isolated`; anything else is passed to `artisan`. A failed `optimize` exits with a clear message instead of a silent crash loop.
- OPcache ini per ADR-0022. The `uv` extension for Reverb (devops-M4).
- Caddy: admin on `localhost:2019`, metrics on a separate `:9180` site, the corrected `request>uri query` log filter, security headers and `request_body max_size`.

### D11. API documentation (arch-12, arch-adr-5, ADR-0015)

`dedoc/scramble` replaces the in-house generator. The export stays at `openapi/openapi.json` so the spec lint and links keep working. Security schemes: the Sanctum session cookie (with CSRF), Player Token bearer and Integration Token bearer, with abilities listed per operation through Scramble's operation transformers. The coverage test compares `route:list` against the export and fails on any missing `/api` route; a second check fails when an API Resource class is not referenced by any schema. AsyncAPI stays hand-written, with the coverage test extended to channels and to payload schema validation against presenter output for factory fixtures.

### D12. Operator documentation (devops-M5, devops-M9, code-M3, devops-M7)

The README gains, in this order: requirements; first deploy (directories to create, `PUID`/`PGID`, `.env` minimum, `docker compose up -d --wait`); reverse proxy (what must be forwarded, a Caddy example, no Traefik); trusted proxies and what the default means for the metrics allow-list and IP rate limits; observability (Pulse, Horizon, Prometheus scraping with token and path, optional OpenTelemetry collector); resource limits (how to add `mem_limit`, `cpus`, `pids_limit`, with starting values from the load test); backup and restore (`mariadb-dump` from the database container, restore steps, Redis AOF note); upgrading (from v2: back up, deploy, migrations run automatically, re-enter social provider credentials, reissue Integration Tokens; from earlier v3: new ports and roles, reissue Integration Tokens).

## Test seams

Tests sit at the highest seam that shows the behaviour, reusing what exists. Seven seams cover the change:

1. **HTTP through routes.** Pest feature tests calling web and API routes as a user, a Player Token or an Integration Token, with the Fake Music Provider, Fake Player and `Http::fake`. Covers auth, tokens, CSRF, logout, signup, rate limits, trusted proxies, metrics access, Queue order and Score, query counts, and documentation coverage. Prior art: `tests/Feature/Party/*`, `Queue/*`, `IntegrationTokenTest`, `MetricsTest`, `OpenApi/OpenApiTest`.
2. **Realtime boundary.** Broadcasts captured with `Event::fake`/`Broadcast` fakes and checked against the AsyncAPI document; channel authorisation through `POST /broadcasting/auth`; Player frames fed to `HandlePlayerClientEvent` as Reverb `MessageReceived` events with a fake connection; connection termination through the `RealtimeConnections` fake. Covers after-commit broadcasting, member events, revocation and payload contracts. Prior art: `Queue/PartyChannelBroadcastTest`, `Playback/PlayerChannelTest`, `Party/PartyModeratorsChannelTest`, `Playback/PlayerFrameListenerTest`, `AsyncApi/AsyncApiCoverageTest`.
3. **Jobs and the schedule.** Dispatchers and jobs run with `Queue::fake` or synchronously, a frozen clock, the Fake Player and `Http::fake` recording Provider calls. Covers per-Party ticks, overlap and lock expiry, ambiguous enqueue, Spotify backoff, frame drops and Horizon config. Prior art: `Playback/PlaybackCoordinatorTest`, `Playback/JobQueuesTest`, `Queue/TopUpFallbackRequestsTest`.
4. **Migrations.** `migrate` run on SQLite from empty, from a v2 schema fixture and from a pre-squash v3 fixture with data, plus constraint tests. New; nearest prior art is `LegacySchemaRemovedTest`.
5. **Architecture.** Pest `arch()` and `ArchitectureRules` for context ownership, cross-context writes, `broadcastAs`, removed directories and packages, and conventions. Prior art: `tests/Architecture/ArchitectureTest.php`.
6. **Deployment config.** The `DeployConfig` helper (1.5) parses Compose files, workflows, env files, the Dockerfiles, the Caddyfile and the PHP ini, and tests assert structure, not text. Prior art: `ReverbComposeConfigTest` (rewritten), `MinimalEnvDefaultsTest`. Container behaviour no unit test can prove (running uid, refused admin port, ulimit, start-up order, Redis persistence, multi-arch manifest) is checked once by hand in the PR that changes it, following the steps in the task.
7. **Vue pages.** Vitest with mocked Echo and Inertia router for pages applying payloads, merging member state, resync, toasts and the POST logout. Prior art: `resources/js/__tests__/PartyShow.test.js`, `PartyTv.test.js`, `RealtimeResync.test.js`.

## Task group ordering (drives ticket `blocked_by` edges)

| Group | Blocked by | Why |
|---|---|---|
| 1. Test infrastructure and schema | none | Everything else needs a green suite on SQLite. 1.2 needs 1.1 (the old migrations do not run on SQLite). 1.3 and 1.4 need 1.1. 1.5 is independent |
| 2. Contexts and conventions | 1.1, 1.2 | Moves almost every file. 2.2 to 2.7 need 2.1 |
| 3. Identity, tokens and access | 2 | Edits moved models and Actions. 3.2 needs 3.1. 3.8 also needs 4.3 and 4.4 (pages joining presence, `member.banned`) |
| 4. Realtime state | 2 | 4.2 and 4.3 need 4.1. 4.4 needs 4.3. 4.5 and 4.6 are independent within the group |
| 5. Playback and queue | 2 | 5.2 and 5.3 need 5.1 (same coordinator and queue). 5.7 needs 5.1 (new `ticks` supervisor) and 6.3 (grace periods) |
| 6. Containers and deployment | 1.5 | 6.3 needs 6.1 (roles). 6.4 needs 6.3. 6.5 needs 6.1 (extension in the image). 6.6 and 6.7 are independent |
| 7. CI, publishing, API docs | 1.2 | 7.2 needs 7.1. 7.3 needs 2.1 and 3.1 (moved classes, token schemes). 7.4 needs 2.7 and 4.4 |
| 8. Docs and wrap-up | 8.1 needs 3.6, 6.x and 7.2; 8.2 needs all | Documents the final behaviour |

Groups 3, 4 and 5 run in parallel after group 2. Group 6 runs in parallel with everything after 1.5.

## Risks / Trade-offs

- **The baseline drops a v2 database's data, including users and social provider credentials.** → Accepted by the user. The README tells Operators to back up first and re-enter provider credentials.
- **No MariaDB in any test.** → Accepted (ADR-0024). Mitigated by schema-builder-only migrations, the manual MariaDB `migrate:fresh` in 1.1, and the `migrate` role failing loudly at deploy.
- **The Redis Lua script for search limits is no longer tested in CI.** → It is small and unchanged. The `redis` group keeps the test for manual runs.
- **Terminating a user's connections disconnects all their tabs, in every Party.** → They reconnect within a second or two and resync. Bans and demotions are rare.
- **A revoked Player keeps receiving commands until its next frame.** → A few seconds. Commands carry only track ids and controls.
- **Trusted proxies default to `*`.** → Accepted by the user. The README states the condition under which the metrics IP allow-list holds; the bearer token does not depend on it.
- **Reverb accepts any origin by default.** → Accepted. Channel authorisation still applies; Operators can restrict.
- **The context move is a large diff.** → It is mechanical, lands first, and the suite plus the architecture test catch mistakes.
- **Scramble inference may need hints.** → Hints are PHPDoc on Resources and controllers. The coverage test fails until they are complete.

## Migration Plan

1. Merge group 1 and group 2 to `feature/v3-rewrite` behind green CI.
2. Merge groups 3 to 7 as they finish. Each is deployable on its own.
3. Operator, on deploying the result: back up the database; rotate the OpenObserve credential from `collector.yml` and run their own collector if they want OpenTelemetry; create bind-mount directories for Redis and `storage/app`; set `PUID`/`PGID`; point the reverse proxy at port 8080 (was 80); set `TRUSTED_PROXIES`, `REVERB_ALLOWED_ORIGINS` and the API token lifetimes if the defaults do not fit; reissue Integration Tokens; after a v2 upgrade, re-enter social provider credentials.

Rollback means redeploying the previous image and restoring the backup, because the baseline and later migrations are not reversible with data.

## Open Questions

- Score display (D8): is "weighting" the Score Modifier adjustments and "lottery/raffle mode" the existing weighted selection mode? If the user meant a third mode, D8 and the Score requirement change.
- Should the v2 upgrade keep `users`, `linked_accounts` and `social_providers` instead of dropping everything? The design drops all, which the user allowed; keeping them is possible if their v2 and v3 shapes match.
- Player Token default lifetime: is 30 days right for a Soloist install that runs across many Parties, or should Player Tokens default to no expiry like Integration Tokens?
- Act-as-Host expiry default of 120 minutes.
