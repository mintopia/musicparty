# Proposal

## Why

A codebase audit of `feature/v3-rewrite` on 2026-10-10 produced 31 findings. The worst come from v1/v2 code that still runs beside v3: a live Spotify session cookie committed to git, an open `/proxy` that writes Spotify tokens to the log, an unauthenticated Soloist webhook that lets anyone burn a Host's Spotify quota, and a legacy voting API that ignores membership, Bans and Party state. Others break production. With the shipped config, browsers try to reach Reverb at `ws://reverb:8080`. None of the Prometheus metrics work. Stats are recomputed inside every vote request, Player objects stay cached forever in Horizon workers, and a failed enqueue leaves the Party in dead air. Two v3 tasks were also never delivered: spec linting in CI (1.6) and the planned Horizon queues (1.8). This change fixes all of them except #4 (won't fix), so v3 can be released.

## What Changes

- **BREAKING** All v1/v2 code is removed (ADR-0011): the legacy request and vote API, the webhooks, `/proxy`, `cookies.json`, `party:*` commands, `app/Services` (except the social login providers and the spec tooling), observers, legacy events, the Spotify-SDK methods on `Party` and `User`, legacy models, and their tables and columns. Anything still needed is rebuilt in v3 first. #1, #2, #3, #8, #9
- The Fallback Playlist and History Playlist are completed in v3. The playlist picker writes `fallback_playlist_id`, a v3 listener appends each Track to the History Playlist when it starts playing, and the Fallback gate's no-repeat check reads `plays`. #13
- One store each for ratings and the Party Log. `PlayRating` and `RateNowPlaying` are removed, and the UI rates the current Play through `RatePlay`. The `party_logs` table and the `PartyLog` context are removed, and act-as-Host writes to the Party Log the Host can read. #14, #26
- **BREAKING (deployment)** The browser gets Reverb's public host, port, scheme and key at runtime from the root view (ADR-0008). New `REVERB_PUBLIC_*` settings default from `APP_URL`, `VITE_REVERB_*` is gone, and the Compose listen port is fixed. #5, #28
- `.env.example` is cut to the settings that are actually required, with sensible defaults in config. `BROADCAST_DRIVER=reverb`, the `PUSHER_*` variables and the `pusher` connection are removed, and Echo is guarded when no key is set. #29
- Prometheus metrics work and include Live Stats, and the endpoint denies access by default (ADR-0009). The runtime image drops `node_modules`. #27
- Party pages require membership: a non-member is sent to the join form instead of being auto-joined. Joining, Requests and Votes are throttled per user, and search gets a generous global leaky-bucket limit per user (ADR-0010). #12
- Correctness under concurrency: rules are re-checked on locked rows, Votes and Ratings are refused in an Ended Party, the vote and rating lock moves to the Member row (after measuring), Player frames are ordered per Party, the Mod scheduled-action claim and the Browser Player claim are atomic, and Player objects are scoped per job and per request. #6, #17, #22, #23, #24, #25
- Playback resilience: any enqueue failure frees the Up Next Request for a retry. Retries back off, with a Party Log entry for each attempt, and give up with a final entry when the backoff is used up. Only `invalid_grant` marks a Host account as needing relinking. Fallback playlist reads are cached. #10, #11, #18, #21
- Stats are recomputed on a queue and coalesced per Party, instead of inside each vote request. #7
- Phones debounce, jitter and run their realtime reloads asynchronously, and resync after a reconnect or when the page becomes visible again. The TV's sequence guard and its counter with a 1-day TTL are removed. #15, #16
- `Setting` loses its static per-process cache. #19
- CI builds and tests the frontend and lints the OpenAPI and AsyncAPI documents. Horizon runs the queues `player`, `polling`, `broadcast`, `mods-ai` and `default`. #20, v3 1.6, v3 1.8
- Dead config, assets and packages are removed: `public/js/*.min.js`, `config/websockets.php`, the Vite `test` block, `postcss`, `numphp/numphp`, `spatie/laravel-fractal`, `squizlabs/php_codesniffer` (with `phpcs.xml` and its Compose services), `laravel/sail` and `laravel/telescope`. #30, #31

### Out of scope

- #4: the `REVERB_APP_SECRET=swordfish` placeholder (won't fix).
- Renaming the 4-letter party code scheme or making it longer.
- Removing `queue` from the `QueueUpdatedEvent` payload.
- Updating the stale Laravel Boost block in `CLAUDE.md`.

### Operator steps (outside code, not ticketed)

These need the account owner and cannot be done by Harmonic:

1. Revoke the Spotify web session whose cookies were committed in `cookies.json`. Sign that Spotify account out everywhere and change its password. Deleting the file does not invalidate `sp_dc`.
2. Optionally purge `cookies.json` from git history (`git filter-repo --path cookies.json --invert-paths`) and force-push every branch and tag. Forks and existing clones keep the old history, so step 1 is what actually protects the account.
3. Before deploying, set `REVERB_PUBLIC_HOST/PORT/SCHEME` if they differ from `APP_URL`, set `PROMETHEUS_TOKEN` or `PROMETHEUS_ALLOWED_IPS` and point the scraper at it, make the reverse proxy forward `/app` and `/apps` to Reverb, and back up the database before the drop migrations run.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `membership`: viewing a Party requires membership, and joining is throttled.
- `api`: per-user limits for joining, Requests and Votes, and a global leaky-bucket limit for search.
- `requests-queue`: rules and Bans are checked against current state at commit, Votes and Ratings are refused when Ended, and there is one Rating store.
- `party-lifecycle`: one Fallback Playlist setting, History Playlist appends when a Track starts, and act-as-Host and enqueue retries are recorded in the Party Log.
- `players`: enqueue failure backoff, per-Party frame ordering, Player changes take effect in running workers, and an atomic Browser Player claim.
- `music-provider`: only `invalid_grant` marks an account for relinking, and playlist reads are cached.
- `realtime`: runtime client settings, paced client reloads, and resync instead of sequence numbers.
- `stats`: asynchronous, coalesced recomputation.
- `mods`: scheduled actions do not run twice when runs overlap.
- `admin`: Telescope is removed, a Prometheus metrics endpoint is added, and the Host can see act-as-Host sessions.

## Impact

- **Code**: large deletions in `app/Models`, `app/Services`, `app/Observers`, `app/Console`, `app/Http`, `routes`, `config` and `public`. Targeted changes in `app/Domain/{Queue,Playback,Music,Stats,Party,Admin,Mod}`, `resources/js` (`bootstrap.js`, `Party/Show.vue`, `Party/Tv.vue`) and `resources/views/app.blade.php`.
- **Database**: drop migrations for `upcoming_songs`, `played_songs`, `votes`, `song_ratings`, `artist_song`, `songs`, `albums`, `artists`, `party_moderations`, `themes`, `websockets_statistics_entries`, `play_ratings` and `party_logs`, and for the legacy `parties`, `party_members` and `users` columns. A data migration removes the `spotifysearch` social provider.
- **APIs**: v1/v2 routes, `POST /api/v1/parties/{party}/control`, the `/requests/{id}/rating` routes and the legacy channel authorisations in `routes/channels.php` (`party.{party}`, which returns true for anyone, `party.{party}.owner` and `spotifytoken.{userId}`) and the legacy `Party.UpdatedEvent` and `UpcomingSong.*` broadcasts are removed. The public `party.{code}` channel is unaffected because it needs no authorisation. The OpenAPI document is regenerated and the AsyncAPI document edited. `sequence` is removed from queue broadcasts.
- **Dependencies**: removes `jwilsson/spotify-web-api-php`, `numphp/numphp`, `spatie/laravel-fractal`, `squizlabs/php_codesniffer`, `laravel/sail`, `laravel/telescope` and `postcss`. Keeps `spatie/laravel-prometheus` (ADR-0009). Adds no packages.
- **Operations**: new `REVERB_PUBLIC_*`, `PROMETHEUS_TOKEN` and search-limit settings. Horizon supervisors are split by queue. The image is smaller.
