# E5 Reliability

Goal: make playback sync, token refresh and Request/Vote writes correct under concurrency and Spotify failures, without changing what the app does.

- Status: Draft
- Wave: 4, in parallel with E4 Deployment
- blocked_by: E3 Platform (and transitively E1, E2)

## Problem

Party sync and the Spotify integration were written for one request at a time. Today's failure modes:

- **Inverted overlap flag.** `PartyUpdate::middleware()` adds `WithoutOverlapping` only when `config('musicparty.allow_overlapping_updates')` is true (`app/Jobs/PartyUpdate.php:30-35`, `config/musicparty.php:4`, default true). The name says the opposite of what it does. The lock also expires after 4 s, and `dontRelease()` silently drops duplicates.
- **Unlocked state machine.** `Party::updateState()` (`Party.php:128-153`) also runs inline from `webhooks/simple` (when `webhook_should_queue=false`), from `Api\V1\PartyController::control`, from `PartyObserver::created` and from `party:force`. None of these paths hold a lock. `addTracksToQueue()` (`Party.php:356-462`) reads Spotify's queue and then queues a track, so two concurrent runs can both Send to Player.
- **Token refresh race.** `User::getSpotifyApi()` (`User.php:141-178`) refreshes lazily with no lock. It saves only the access token, never a rotated refresh token. It ignores a failed refresh, and a revoked refresh token throws on every run and every minute in `party:refreshaccesstokens`.
- **No rate-limit handling.** Nothing honours a Spotify 429 or `Retry-After` (facts-architecture §7).
- **Broadcast storms.** `PartyObserver::updated` broadcasts `Party\UpdatedEvent` on every Party save, including the poll bookkeeping bump to `last_updated_at`. `broadcastWith()` then rebuilds `getState()` in the queue worker (facts §6).
- **`tries=1`.** Horizon `supervisor-1` runs with `tries=1` (`config/horizon.php:183-195`), so one transient Spotify error drops the poll chain until `party:fallback` notices 90 s later.
- **Known bugs:**
  - `UpcomingSong.php:21` reads `'not_before', 'datetime'` (a comma where `=>` belongs), so `not_before` is never cast. The Queue SQL compares it to the bound string `'NOW()'` (`Party.php:399, 425`).
  - `RequestCheckService::checkSimilarSongs` calls `array_keys($names)` on a list (`RequestCheckService.php:184`), so the similar-song rule never fires.
  - `PartyCheck.php:51` calls `Party::fixPlaylist()`, which does not exist, so `party:check` is broken.
  - `Party.php:684` reads `$this->owner_id`, but the owner column is `user_id`.
  - `PlayedSong::findRequestedSong` (`PlayedSong.php:41-56`) matches the most recently Sent to Player track with the same `spotify_id` across **all Parties** (there is no `party_id` filter), which attributes plays to the wrong requester.
  - There are no unique indexes on `votes(upcoming_song_id,user_id)` or on `songs/albums/artists.spotify_id`. `UpcomingSong::addVote`, `Song/Album/Artist::fromSpotify` and `UpcomingSongController::store` (check, then `getTrack`, then insert) are all check-then-act, which allows duplicate Votes, duplicate catalogue rows, duplicate Requests and a `max_requests` bypass.
  - Whamageddon's `HourTickListener` loops over every Party with no per-Party isolation, so one exception aborts the remaining Parties. It also imports `Laravel\Reverb\Loggers\Log` instead of the app logger.

## Goals

- At most one playback sync runs per Party at any moment, whichever path triggers it.
- At most one track is Sent to Player per selection, with no duplicates.
- Token refresh is serialised per linked account and survives rotation and revocation.
- Spotify 429s back off, honouring `Retry-After`. They do not cause failures or retry storms.
- Broadcasts fire only when state that clients render has changed.
- The listed bugs are fixed, each backed by a test.

## Non-goals

- Extracting services out of `Party`/`User` (E6). Fixes land in the current classes and E6 moves them.
- Pushed playback from the Web Player or Encore (E8). Playback Webhook token auth (deferred).
- Whamageddon's own `votes`/`score_adjustment` setting mismatch and its hard-coded timezone (E8).
- New features, UI changes or behaviour changes beyond correcting bugs.

## Requirements

1. **R1 Overlap flag.** Replace `allow_overlapping_updates` with a flag whose name matches its effect, e.g. `prevent_overlapping_updates`, defaulting to preventing overlap. Remove the old key from config and env examples.
2. **R2 Party sync lock.** Every caller of `updateState()` runs inside one per-Party atomic cache lock (Redis in prod, array in tests), with a TTL longer than a worst-case run. A caller that cannot get the lock skips, or for queued jobs releases with a delay, and never runs concurrently. This covers the inline webhook path, `control`, the observer and `party:force`.
3. **R3 Send to Player is idempotent.** `addTracksToQueue()` runs under the R2 lock, re-reads state inside it, and marks the Request as Sent to Player (`queued_at`) in the same critical section as the Spotify `queue` call.
4. **R4 Debounce is crash-safe.** A run that throws still records its attempt time, so a failing Party is not re-run immediately in a loop.
5. **R5 Token refresh.** Refresh runs under a per-LinkedAccount lock and re-reads the token inside the lock. A rotated refresh token is persisted, and refresh results are checked.
6. **R6 Revoked refresh.** An `invalid_grant` or 400/401 on refresh marks the linked account as needing re-link and stops that Party's poll chain and refresh attempts until re-link. It must not throw every minute. Party managers see a "Spotify needs re-linking" banner on the Party page until the account is re-linked.
7. **R7 429 backoff.** One Spotify client wrapper catches 429 and stores a backoff-until timestamp in the shared cache, keyed per Spotify client application (control app vs `spotifysearch` app), honouring `Retry-After`. While backoff is active, calls short-circuit: jobs release until it expires, and web search shows a friendly error. Other `SpotifyWebAPIException`s keep their current handling.
8. **R8 Job retries.** Party sync jobs retry transient failures with bounded exponential backoff, using job-level `tries`/`backoff` or `retryUntil`, while R2 still guarantees no overlap. Horizon config is updated to match.
9. **R9 Broadcast hygiene.** `Party\UpdatedEvent` dispatches only when a rendered field changes, not on bookkeeping columns such as `last_updated_at`. A burst of changes within one sync produces one broadcast. The broadcast payload is built from the state at dispatch time.
10. **R10 `not_before`.** Cast it with `'not_before' => 'datetime'` and compare it with `now()` as a datetime parameter, never the string `'NOW()'`. This keeps it portable for E2's SQLite tests.
11. **R11 Similar songs.** `checkSimilarSongs` matches on track names. A test proves that a same-name, same-artist waiting Request blocks with the existing reason.
12. **R12 `party:check`.** The command is deleted. It has been broken since `fixPlaylist()` never existed.
13. **R13 Owner check.** `canBeManagedBy` compares against `user_id`.
14. **R14 Play attribution.** `findRequestedSong` is scoped to the Played Track's Party. When several Requests for the same track exist, it links the one marked Sent to Player earliest that has not played, because the player queue is first in, first out. Both the played track id and the relinked id are matched. A play with no sent Request stays unlinked.
15. **R15 Uniqueness.** Migrations add unique indexes on `votes(upcoming_song_id,user_id)` and on `spotify_id` for `songs`, `albums` and `artists`, after de-duplicating existing rows (re-pointing foreign keys to the survivor). `addVote` and `fromSpotify` become atomic, using upsert or an insert-or-select on unique-violation retry.
16. **R16 Request races.** `UpcomingSongController::store` runs its checks and insert under a per-Party-and-Member lock, so neither `max_requests` nor "already requested" can be bypassed by concurrent posts.
17. **R17 Tick isolation.** `HourTickListener`, and any tick listener that iterates Parties, catches and reports per-Party failures and carries on. It uses the app's `Log` facade.

## Acceptance criteria

- Pest tests (mocked Spotify client, in-memory SQLite) prove each item:
  - concurrent `updateState()` calls produce one Spotify `queue` call (simulate by acquiring the lock in the test)
  - a 429 with `Retry-After: 7` sets backoff and the next call makes no HTTP request
  - a revoked refresh marks the account, stops the chain and shows managers the re-link banner
  - a rotated refresh token is saved
  - `not_before` in the future is excluded and in the past is included
  - `findRequestedSong` never crosses Parties
  - duplicate Vote and Request posts yield one row
  - a throwing Party in the hour tick does not stop the next Party
  - `party:check` is no longer registered
  - saving only `last_updated_at` dispatches no `Party\UpdatedEvent` (`Event::fake`)
- The de-dup migrations run cleanly against a copy of a populated MariaDB database, and against SQLite in tests.
- E2's characterization tests still pass, or are updated in the same ticket where the old behaviour was the bug, with the change called out in the PR.
- PHPStan level 8 adds no new baseline entries in the touched files, and existing entries shrink.

## Functionality that must be retained

- Polling (per-Party `poll`) with the `getNextUpdateDelay` cadence, the `party:fallback` restart and `party:force` forced playback.
- All three Playback Webhooks (`librespot`, `simple`, `soloist`) and the E1 per-Party rate limit on `soloist`.
- The `webhook_dispatch_after_request` and `webhook_should_queue` options. Inline mode still works, now locked.
- Weighted and random Queue selection, Fallback Playlist backfill, track relinking, the History Playlist append and `QUEUE_LENGTH = 1` behaviour.
- The `spotifysearch` account for search with fallback to the control account, and the minute-scheduled token refresh.
- Every RequestCheckService rule and its user-facing reason text.
- Whamageddon's hourly behaviour.

## Suggested ticket slices

1. **E5-1** Fix the overlap flag and add the per-Party sync lock across all `updateState` callers, with a crash-safe debounce (R1, R2, R4). blocked_by: none.
2. **E5-2** Make Send to Player idempotent inside the lock (R3). blocked_by: E5-1.
3. **E5-3** Spotify client wrapper with 429/Retry-After backoff in the shared cache (R7). blocked_by: none.
4. **E5-4** Token refresh lock, rotation persistence and revoked-refresh handling (R5, R6). blocked_by: E5-3, because it shares the wrapper.
5. **E5-5** Job retries/backoff and the Horizon config (R8). blocked_by: E5-1, E5-3.
6. **E5-6** Broadcast hygiene: only rendered changes, coalesced (R9). blocked_by: E5-1.
7. **E5-7** `not_before` cast and portable SQL (R10). blocked_by: none.
8. **E5-8** Fix `checkSimilarSongs` (R11). blocked_by: none.
9. **E5-9** Fix the owner check and delete `party:check` (R12, R13). blocked_by: none.
10. **E5-10** Party-scoped play attribution (R14). blocked_by: E5-2, because the link is set at Sent to Player time.
11. **E5-11** Unique indexes with de-dup migrations and atomic `addVote`/`fromSpotify` (R15). blocked_by: none.
12. **E5-12** Request store lock (R16). blocked_by: E5-11.
13. **E5-13** Per-Party isolation in tick listeners (R17). blocked_by: none.

## Risks and open questions

Implementation-level; settle when tickets are written.

- **De-dup migration on production data** re-points foreign keys and could lose history if it goes wrong. Back up first and rehearse on a copy. E6 later replaces `spotify_id` with `provider_id` + `provider_track_id`, so these indexes are short-lived; they are still needed now for correctness.
- **`not_before` overlap with E8.** E5 fixes the core cast and SQL. Hold time is a core Request concept that E8 builds Scheduled Requests and Whamageddon's hold on, treating the core filter as already correct.
- **Lock TTL vs Spotify latency.** Too short reintroduces overlap and too long stalls a Party after a crash. Proposal: TTL of about 30 s, with `block(0)` for webhooks and release-with-delay for jobs.
- **Parallel with E4.** E5 touches `config/horizon.php` and the scheduler definitions moved by E3. E4 touches compose and Caddy, so conflict risk is low, but both may edit env examples.
