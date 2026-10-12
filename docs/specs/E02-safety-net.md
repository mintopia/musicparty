# E2 Safety net

Pin current behaviour with characterization tests and add the quality gates and CI every later epic relies on.

- **Status:** Draft
- **Wave:** 2
- **blocked_by:** E1

## Problem

- Effectively no tests exist: `tests/Feature/ExampleTest.php` (GET `/` is 200) and a Laravel stub in `tests/Unit/ExampleTest.php`, both PHPUnit-style although Pest v3.8.2 is installed. E1 adds only the security tests and the SQLite guard.
- `phpunit.xml` had its DB lines commented out, so `RefreshDatabase` could have hit whatever database `.env` pointed at.
- The Spotify client is built inline: `User::getSpotifyApi()` (`User.php:141`) and separately `SpotifySearchService::getApi()` (`SpotifySearchService.php:24-61`). There is no seam for a fake, so any test touching playback, search or requests would call Spotify.
- Randomness is global: queue selection uses `mt_rand` (`Party.php:405`) and the Fallback Playlist uses `shuffle` (`Party.php:631`).
- No static analysis or formatting gates: no PHPStan/Larastan, no Rector, no `pint.json`; only `phpcs.xml` (PSR-12 on `app/`).
- CI is only `.github/workflows/publish-docker-images.yml` (image publish). Nothing runs tests, lint or analysis.
- Some SQL is MariaDB-specific and will surface when migrations and queries run on SQLite.

Every later epic (E3 upgrades, E5 race fixes, E6 extraction) refactors code that has no tests. This epic makes those changes safe.

## Goals

- Characterization tests over the core pipelines, run against a mocked Spotify client and in-memory SQLite.
- A base test case that makes touching any other database impossible.
- Pint, Larastan level 8 with a baseline, and Rector configured and enforced.
- A GitHub Actions workflow that runs tests, lint and static analysis on every push and PR.
- No flaky tests; tests run in parallel.

## Non-goals

- Fixing known bugs. Characterization tests pin today's behaviour, bugs included; E5 fixes them and flips the tests.
- Refactoring production code beyond the minimum seams needed to test it.
- Frontend (Vue) unit tests.
- A coverage threshold gate. Coverage is reported, not gated.

## Requirements

### Harness
1. A base `TestCase` that fails the test run if the default connection is not SQLite `:memory:`, and refuses to open any other connection.
2. All migrations run on in-memory SQLite. MariaDB-only SQL found in migrations or queries is rewritten to be portable without changing behaviour on MariaDB.
3. Tests are Pest syntax. The two stubs are already converted by E1.
4. `php artisan test --parallel` works, with the process count capped so CPU load stays reasonable.
5. Factories exist for every model the tests use (at least Party, PartyMember, User, LinkedAccount, SocialProvider, Song, Album, Artist, UpcomingSong, PlayedSong, Vote, SongRating, PartyModeration, Mod, ModSetting, PartyModSetting), with states for roles (owner, VIP, user, banned) and Request states (waiting, Sent to Player).

### Determinism
6. No test makes a network call. Outbound HTTP is blocked (`Http::preventStrayRequests()`), and the Spotify client is resolved through the container so tests bind a fake or mock.
7. `User::getSpotifyApi()` and `SpotifySearchService::getApi()` obtain their `SpotifyWebAPI` instance from the container. Production behaviour is unchanged.
8. Time is frozen or travelled explicitly; randomness is seeded or injected so queue-selection tests are exact.
9. No sleeps, no reliance on test order, no shared state between parallel processes.

### Characterization coverage
10. **Requests:** `UpcomingSongController::store` and `RequestCheckService` rules: not accepting requests, banned, max requests (VIP exempt), min/max length, explicit, `no_repeat_interval`, "will be played next", Blocklist rules (each `PartyModerationType`), similar-song check.
11. **Votes:** `UpcomingSong::addVote` (including banned), score updates, the downvote throttle (`Party.php:697`), ratings on Played Tracks.
12. **Queue selection:** `Party::addTracksToQueue` weighted random choice, `not_before`, `score_adjustment`, Fallback Playlist top-up, the "queue repeats context" handling, and what is Sent to Player.
13. **Playback sync:** `Party::updateState` and `updateCurrentSong` against canned Spotify playback responses, including relinking and Played Track creation.
14. **Search augmentation:** `SpotifySearchService::search` result marking (`upcoming`, `score`, `vote`, `user`, `allowed`, `reason`) and the Spotify Search account fallback to the control account.
15. **Playback Webhooks:** librespot, simple and soloist routes, their auth and the jobs they dispatch.
16. **Realtime:** each broadcast event's channel name, channel type and payload shape; `routes/channels.php` authorisation.
17. **Auth and merge:** social login create, link, verified-email merge (E1 rules), login-disabled providers, impersonation start/stop, first-user-becomes-admin.
18. **Mods:** Whamageddon install, add-to-Party, hourly score bump, overnight hold, re-add after play, settings listeners.
19. **Scheduler:** every scheduled entry is registered with its frequency and `onOneServer`; `party:fallback`, `party:force`, `party:refreshaccesstokens` and the tick events do what they do today.
20. Tests that pin a known bug (as listed in E5) are named for the current behaviour and reference the E5 item, so E5 can find and flip them.

### Quality gates
21. `pint.json` with the Laravel preset; the codebase is formatted with it. Pint is the only style tool: `phpcs.xml` and the compose `phpcs`/`phpcbf` profiles are removed.
22. Larastan at level 8 with `phpstan-baseline.neon` holding today's errors. New code adds no errors; the baseline only shrinks.
23. `rector.php` configured for the current PHP and Laravel versions; CI runs it in dry-run mode and fails on pending changes.
24. A GitHub Actions workflow runs on push and PR for `feature/v3-rewrite`, `epic/**` and `develop`: Pest in parallel, `pint --test`, PHPStan, Rector dry-run, and `npm ci && npm run build`. It reports test coverage (xdebug) without a threshold. It needs no secrets and no external services.
25. Composer scripts (`test`, `lint`, `analyse`, `rector`) let developers run each gate locally.

## Acceptance criteria

- `php artisan test --parallel` passes repeatedly (at least 20 consecutive runs, including in CI) with no network access.
- Pointing `DB_CONNECTION` at MariaDB makes the suite refuse to run.
- Every area in requirements 10-19 has tests; each requirement's area maps to at least one test file.
- The new CI workflow is green on the epic branch and fails when a test, Pint, PHPStan or Rector check fails (demonstrated once with a throwaway commit). It publishes a coverage report.
- `phpcs.xml` does not exist and no compose profile runs phpcs.
- Production behaviour is unchanged: the local deployment works as before.

## Functionality that must be retained

All of it. The only production-code changes allowed are the container-resolved Spotify client, portability rewrites of SQL that behave identically on MariaDB, Pint formatting, and whatever minimal seams a test needs, each kept behaviour-neutral.

## Suggested ticket slices

| # | Slice | blocked_by |
|---|---|---|
| 1 | Pint config, phpcs removal and one-off formatting pass (req 21) | none |
| 2 | Base test case, SQLite migrations portability, parallel runs (req 1-4, 9) | 1 |
| 3 | Model factories and states (req 5) | 2 |
| 4 | Spotify client container seam, fake, stray-request block, seeded randomness (req 6-8) | 2 |
| 5 | Characterization: Requests, votes, ratings (req 10, 11, 20) | 3, 4 |
| 6 | Characterization: queue selection and playback sync (req 12, 13, 20) | 3, 4 |
| 7 | Characterization: search augmentation (req 14) | 3, 4 |
| 8 | Characterization: Playback Webhooks and realtime (req 15, 16) | 3, 4 |
| 9 | Characterization: auth, merge, impersonation (req 17) | 3 |
| 10 | Characterization: Whamageddon and scheduler (req 18, 19) | 3, 4 |
| 11 | Larastan level 8 with baseline (req 22) | 1 |
| 12 | Rector config, applying only rules that are behaviour-neutral (req 23) | 5, 6, 7, 8, 9, 10 |
| 13 | GitHub Actions workflow and composer scripts (req 24, 25) | 2, 11, 12 |

Slices 5-10 run in parallel. Slice 1 lands first because it touches most files.

## Risks and open questions

Implementation-level; settle when tickets are written.

- **`'NOW()'` bound as a string** (`Party.php:399`, `:425`). It does not error on SQLite, so it is not a portability problem; it is a behaviour bug owned by E5. E2 pins it, it does not fix it.
- **Characterizing a bug vs. fixing it.** Where a characterization test exposes a security issue E1 missed, raise it rather than fixing silently.
- **Rector scope.** Which rule sets to enable (dead code, type declarations, Laravel set) is undecided; start conservative.
- **Spotify fixtures.** Canned responses must resemble real SDK v6 objects (stdClass). E3 moves to SDK v7; the fixtures may need updating there.
- **Spotify-dependent observers.** `PartyObserver::created` calls `updateState()` on save, so creating a Party in a test calls Spotify. The fake must be bound before any factory creates a Party.
