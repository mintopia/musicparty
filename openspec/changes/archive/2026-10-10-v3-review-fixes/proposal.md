# Proposal

## Why

A code, architecture and DevOps review of `feature/v3-rewrite` on 2026-10-10 produced 69 findings. The user decided to fix 68 and not fix one (devops-M8, baked production env defaults, which are intentional).

The worst are these. Running the test suite locally wipes the developer's database. Banning a Member, demoting a Moderator or revoking a Player Token does not cut off their live realtime access. A collector credential is committed in plaintext. Containers run as root with Caddy's admin API open to the network. Redis loses every session and queued job on redeploy, and Horizon is killed mid-job on every deploy. Every Queue change makes each phone reload over HTTP, about 500 requests per change at the target Party size. Playback ticks run serially for every Party in one job with no overlap guard. The Queue shown to Members can differ from the order selection uses. Eleven decisions have no ADR, and in four of them (the custom token guard, the in-house OpenAPI generator, poke-then-reload broadcasts and unrevoked subscriptions) the user chose to replace the thing rather than record it.

## What Changes

- **BREAKING (development)** Tests run on in-memory SQLite and need no other service, locally and in CI (ADR-0024). code-H1
- **BREAKING (database)** The migration history is replaced by one portable v3 baseline. It installs fresh, upgrades a v2 database (v2 data is dropped), and leaves an existing v3 database alone. The database enforces one linked account per social identity and one Up Next and one Playing Request per Party. arch-14, code-M1
- Models, events, jobs and listeners move into their bounded contexts, with a new Membership context, and the architecture test enforces ownership by detecting cross-context writes (ADR-0012). Convention drift is fixed and PHPStan's baseline loses its missing-type entries. Authorisation goes through Policies. The Music Provider seam stops carrying Player concerns. `spatie/eloquent-sortable` and the direct `ramsey/uuid` requirement are removed (ADR-0017, ADR-0020). arch-adr-1, arch-4, code-L5, code-L11, arch-13, arch-6, arch-11, arch-adr-7, arch-adr-10, arch-15
- **BREAKING (API)** Integration Tokens become Sanctum tokens and the custom guard is removed (ADR-0014). Existing Integration Tokens must be reissued. Player and Integration Tokens can expire, `last_used_at` is throttled, and act-as-Host sessions expire. arch-10, arch-adr-3, arch-adr-4, code-L8
- **BREAKING (web)** Logout is POST. Signup must be completed before using the app; terms acceptance is required only when a terms URL is configured. CSRF is skipped on `broadcasting/auth` only for a valid token. Trusted proxies are configurable (default `*`). Code-addressed public routes are throttled per address. code-L1, code-M2, code-L2, code-M3, code-L9
- Banning, demoting or suspending a user terminates their realtime connections, and a revoked Player Token's connection is closed on its next frame (ADR-0019). arch-1, arch-adr-9
- Queue broadcasts carry state that clients apply directly, built by the same serializer as the API, with each Member's own Votes and Ratings on their member channel (ADR-0018). The Score shown is the Score that orders the Queue and drives selection. Every broadcast is wired to a page or deleted, has an explicit wire name, and is sent only after commit. Failing decoration providers log once a minute instead of on every read. arch-3, arch-adr-8, code-M8, arch-2, arch-5, devops-L7, code-M7
- Playback ticks and scheduled Mod actions run as one job per Party with no overlap, an expiring lock and no Provider call unless there is something to hand off. An ambiguous enqueue is not re-sent. Per-row queries on hot paths are removed and query counts are asserted. The Playing Track counts for no-repeat. A failing Blocklist regex blocks and is logged. Horizon retries broadcast and default jobs, snapshots, and runs in any environment. Dropped Player frames are counted (ADR-0013). The shared Spotify backoff is recorded and tested (ADR-0021). code-M5, arch-7, code-M6, code-M4, code-L6, code-L3, code-L4, code-L7, devops-L6, arch-9, arch-adr-2, arch-adr-11
- **BREAKING (deployment)** One image and one entrypoint for every role. Containers start as root, take `PUID`/`PGID`, chown what they need and drop privileges. FrankenPHP listens on 8080, and Caddy's admin API is local to the container. The example Compose gains healthchecks, a one-shot migrate service, ordered start-up, stop grace periods, Redis append-only persistence on a bind mount, and bind mounts only. The Traefik override is deleted. Images move to the latest stable releases. OPcache is tuned and the JIT stays off (ADR-0022). Reverb gets an event-loop extension, a raised file limit and configurable origins (default `*`). Cache and sessions default to Redis. `collector.yml` and its credential are removed; OpenTelemetry exports to a collector the Operator runs (ADR-0016, ADR-0023). devops-H1 to H5, devops-M1 to M7, devops-M9, devops-L3 to L5, devops-L8, devops-L9, code-L10, arch-8, arch-adr-6
- CI and publishing: actions pinned by SHA with least-privilege permissions, image scanning, SBOM and provenance, immutable tags, publishing only after green CI, and native arm64 builds merged into one manifest. devops-L1, devops-L2
- The in-house OpenAPI generator is replaced by `dedoc/scramble`, and every API route, Resource, channel and event must be documented or CI fails (ADR-0015, superseding ADR-0002). arch-12, arch-adr-5
- The README documents deployment, reverse proxy (Caddy example), trusted proxies, observability, resource limits, backups and upgrading.

### Out of scope

- devops-M8: baked production env defaults (won't fix; they are intentional and overridable from Compose).
- Authenticating the TV screen or the public channel, or lengthening the party code (accepted risk, ADR-0019).
- Restricting Reverb origins or trusted proxies by default.
- Shipping resource limits, backup jobs or a reverse-proxy stack.
- Keeping v2 data through the upgrade.
- MariaDB or Redis in any test or CI job.
- Changing the `swordfish` `REVERB_APP_SECRET` placeholder, the internal app-to-Reverb address or the minimal `.env.example`.

### Operator steps (outside code, not ticketed)

1. Rotate the OpenObserve ingest credential that was committed in `collector.yml`, and purge it from git history if the repository is or becomes public.
2. Before deploying: back up the database, create the bind-mount directories (`database`, `redis`, `storage/app`, `logs`), set `PUID`/`PGID` to their owner, and repoint the reverse proxy at port 8080.
3. After deploying: reissue Integration Tokens; after a v2 upgrade, re-enter social provider credentials.

## Capabilities

### New Capabilities

- `operations`: isolated test suite, database install and upgrade, unprivileged containers, one entrypoint, ordered start-up, graceful shutdown, bind-mount persistence, Redis-backed shared state, trusted proxies, telemetry export, web hardening, published images and Operator documentation.

### Modified Capabilities

- `realtime`: Party state on the public channel, broadcasts after commit, own Votes and Ratings on the member channel, live Pending and Party Log pages, revocation closes connections, clients apply payloads from one serializer, explicit wire names, configurable origins, connection capacity. "Paced client refreshes" is removed.
- `api`: Scramble-generated documentation of every route and Resource, both token kinds on Sanctum with expiry, CSRF only skipped for valid tokens, throttled public routes.
- `players`: Player Token expiry and revocation of live connections, visible dropped frames, no re-send of an ambiguous hand-off, per-Party ticks and scheduled Mod actions.
- `requests-queue`: one Score shown and used, Playing Track counts for no-repeat, failing regex blocks, Blocklist read once per top-up, one active Track per Party in the database.
- `membership`: one user per social identity, POST logout, throttled public routes, signup completion with optional terms.
- `admin`: Horizon snapshot and environments, dev-only Debugbar, metrics client address from trusted proxies and dropped-frame metric, act-as-Host expiry.
- `mods`: decoration failures logged once a minute.

## Impact

- **Code**: every file under `app/Models`, `app/Events`, `app/Jobs`, `app/Listeners` and `app/Services` moves into `app/Domain/<Context>`. New Membership context, `QueueEntryPresenter`, `RealtimeConnections`, `TickParty`, `RunPartyScheduledActions`, `EnsureSignupComplete`, `Integration`. Removed: `IntegrationTokenGuard`, `OpenApiGenerator`, `RuleSchemaMapper`, `GenerateOpenApi`, `MessageLoggedListener`. Frontend: `Party/Show.vue`, `Tv.vue`, `Pending.vue`, `Log.vue`, `bootstrap.js`, the logout control.
- **Database**: one baseline migration replaces 79; new migrations for `linked_accounts` constraints, generated active-track columns, dropping `integration_tokens`, `admin_host_sessions.expires_at`.
- **APIs**: Integration Tokens reissued as Sanctum tokens; new member-scoped votes endpoint; signup-required error code; 429 on public code-addressed routes; OpenAPI regenerated by Scramble; AsyncAPI wire names change to explicit dotted names, with new `party.state_changed`, `member.vote_changed`, `member.rating_changed` and `member.banned` events.
- **Dependencies**: adds `dedoc/scramble` and `symfony/yaml` (dev, already locked). Removes `spatie/eloquent-sortable` and the direct `ramsey/uuid` requirement. Adds the `uv` PHP extension to the image.
- **Operations**: new `PUID`, `PGID`, `TRUSTED_PROXIES`, `REVERB_ALLOWED_ORIGINS`, token lifetime and act-as-Host settings; `CACHE_DRIVER` renamed to `CACHE_STORE`; web port 80 → 8080; new `migrate` and `ticks` roles and queues; `collector.yml` removed.
