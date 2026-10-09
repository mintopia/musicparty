# Tasks

Each checkbox is roughly one PR, done in its own worktree off `feature/v3-rewrite`. Each task lands its own Pest tests, mocked through the Fake Music Provider or the Fake Player wherever a Provider or Player is involved, and must pass PHPStan level 8, Pint and Rector. While working, run only the affected tests. Run the full suite before merging. Flaky tests block the merge. Any new `/api` route or broadcast must be added to the OpenAPI and AsyncAPI documents in the same PR, because the CI checks from 1.5 and 1.6 enforce it.

## 1. Foundation

- [ ] 1.1 Remove the v2 leftovers (imports of nonexistent controllers and observers, seat_* theme fields, YouTube, the old Mods framework, `css_classes`). Verify that `php artisan route:list` and the test suite run clean.
- [ ] 1.2 Remove Blade views (except the Inertia root view), Tabler/Bootstrap/Sass and Livewire if present. Verify that `composer show` and `npm ls` no longer list them and that `npm run build` succeeds.
- [ ] 1.3 Install Inertia (`inertiajs/inertia-laravel`, `@inertiajs/vue3`) with Vue 3 and Vite, add the root view, `HandleInertiaRequests` middleware and a placeholder page. Verify with a Pest test that `/` returns an Inertia response for the page component.
- [ ] 1.4 Add Tailwind v4 with a `@theme` token sheet (`--mp-color-*`, `--mp-font-*`). Defaults are the v2 Tabler values, with light and dark values toggled by `data-scheme`. Verify that the build output contains the variables and that a Vitest or Pest test checks the token list against the token schema.
- [ ] 1.5 Add the code-first OpenAPI generator (ADR-0002) and commit `docs/api/openapi.json`. Add the Pest tests that fail on spec drift and on any undocumented `/api` route. Verify by adding an undocumented route in a test fixture and seeing it fail.
- [ ] 1.6 Add the `docs/api/asyncapi.yaml` skeleton and a Pest reflection test that fails when any `ShouldBroadcast` event or Player message type is missing from it. Lint both specs in CI. Verify with a fixture event that is deliberately left undocumented.
- [ ] 1.7 Set up `app/Domain/*` bounded-context directories and Pest architecture tests (no cross-context model writes, no mutable statics in `app/Domain`, broadcast events never serialise models, Provider tokens never appear in broadcast payloads). Verify that `php artisan test --filter=Arch` passes.
- [ ] 1.8 Configure CI: Pest (parallel), PHPStan level 8, Pint, Rector, coverage via xdebug, and the spec checks. Configure Horizon queues `player`, `polling`, `broadcast`, `mods-ai` and `default`. Verify that a CI run on the PR is green.

## 2. Domain core

- [ ] 2.1 Party model, factory, 4-letter party code generation, and the `CreateParty` Action. Verify with a feature test for code format and uniqueness, and with a test that only users holding the create-party role can create.
- [ ] 2.2 Party states Live, Paused and Ended with `GoLive`, `PauseParty`, `EndParty` and `ReopenParty` Actions and Policies. Verify with state transition tests, including that only the Host can reopen an Ended Party.
- [ ] 2.3 Party Log model, writer and Policy (Host and Moderators only). Verify that tests show each logged action type is recorded and that Guests are denied access.
- [ ] 2.4 Membership: join by code, Party Roles (Host, Moderator, VIP, Guest), role assignment, Ban as a status, and Socialite login (Discord, Twitch, Steam, Spotify) with provider credentials read from encrypted DB rows, seeded by an artisan command. Verify with feature tests for join, the role permission matrix, Ban effects, and login using a mocked Socialite.
- [ ] 2.5 Instance roles (admin, create-party) and the act-as-Host session, recorded in the Party Log. Verify with tests that act-as-Host grants Host permissions and that the session start and end are logged.
- [ ] 2.6 Request, Vote, Play and Rating models with the lifecycle Pending → Queued → Up Next → Playing → Played and the exits Rejected and Removed. Verify with tests for allowed and forbidden transitions.
- [ ] 2.7 The `RequestRule` contract and the core rules: allow_requests, max per Member (VIP and Host exempt), min/max length, explicit filter, Blocklist (all match types, regex, enable flag), no-repeat interval, already-Up-Next, similar-track dedupe, duplicate-as-upvote, and requester auto-upvote. Verify with a dataset-driven Pest test per rule.
- [ ] 2.8 Pending approval (opt-in per Party) with `ApproveRequest`, `RejectRequest` and `RemoveRequest` Actions for the Host and Moderators. Verify with tests for the holding rule, permissions and Party Log entries.
- [ ] 2.9 Votes: one per Member per Request, downvotes disabled per Party, per-hour downvote cap, and Banned Members blocked. Score is the sum of Votes. Verify with tests for each limit.
- [ ] 2.10 Selection strategies `DeterministicSelection` and `WeightedRandomSelection` (seeded random source, `not_before`) and the `LockUpNext` Action under a Party row lock. Verify with deterministic tests and a concurrency test that only one Up Next gets locked.
- [ ] 2.11 Ratings (like or dislike on a Play) and the Live Stats event hooks (domain events only). Verify with tests for one Rating per Member per Play and for Banned Members being blocked.

## 3. Spotify Provider

- [ ] 3.1 The `MusicProvider` contract, `TrackData` normalisation, and `FakeMusicProvider` bound in tests. Verify with contract tests that run against the fake.
- [ ] 3.2 Spotify catalogue search and Track lookup using instance client credentials (cached token, market). Verify with tests against a mocked HTTP client using recorded fixtures.
- [ ] 3.3 Host account linking for Spotify, plus an encrypted token store with refresh. Verify with tests for linking, refresh before expiry, and the encrypted cast, and confirm that only the Host links an account.
- [ ] 3.4 Fallback Playlist: readiness check (at least 20 eligible Tracks under Party rules), re-validation when the playlist changes, Queue top-up to the minimum with shuffle and recent-skip, and exhaustion handling (warn the Host, re-allow recent Plays, then stop). Verify with feature tests for the `GoLive` gate and each exhaustion step.
- [ ] 3.5 History Playlist append on each Play. Verify with a test that the mocked Spotify client receives the append and that it is skipped when the History Playlist is disabled.

## 4. Players

- [ ] 4.1 The `Player` contract, Compatibility, Feed Modes, `PlaybackState`, `FakePlayer`, the `PairPlayer` Action, and the `PlaybackCoordinator` (Ahead and JustInTime, stop instead of letting autoplay take over). Verify with coordinator tests driven by the FakePlayer for both Feed Modes and for the empty-Queue stop.
- [ ] 4.2 Player Tokens: Sanctum tokens scoped to a Party with the `player:connect` ability, issue and revoke Actions for the Host, and Party Log entries. Verify with tests for scope, revocation and that non-Hosts are denied.
- [ ] 4.3 Polling Player: a self-rescheduling job on the `polling` queue with `WithoutOverlapping` per Party and adaptive delay, and Ahead feed into Spotify's queue. Verify with tests using a mocked Spotify client and `Queue::fake`.
- [ ] 4.4 Webhook endpoint authenticated by a Player Token, which only triggers an immediate poll. Verify with feature tests for 401 without a token, 403 with a revoked or wrong-Party token, and a dispatched job on success. This fixes the v2 unauthenticated webhook.
- [ ] 4.6 Pusher-protocol relay support in `../musicparty-soloist`: connect to Reverb, authenticate `private-player.{id}` with a Player Token, and wrap native frames as client events. Verify with that repository's tests against a mock Pusher server.
- [ ] 4.7 The `private-player.{playerId}` channel authorisation and the Reverb listener (validate envelope, size and rate limits, then dispatch `HandlePlayerMessage` to the `player` queue). Verify with tests that a client event on any other channel is ignored and that no domain code runs in the listener (architecture test).
- [ ] 4.8 `SoloistAdapter`: parse `playback_state`, `track_changed`, `queue_changed` and `command_result`, send `add_to_queue` and the control commands, guard against autoplay or context items, and Party Log the connection health. Verify with adapter tests driven by recorded Soloist frames.
- [ ] 4.9 Browser Player: a Vue Web Playback SDK page for the Host, a short-lived token delivered only by Inertia prop or a Host-only private channel, and state reported through the API. Verify with feature tests that no token appears on any public channel or page for non-Hosts. This fixes the v2 public token broadcast.

## 5. API & realtime specs

- [ ] 5.1 `/api/v1` authentication: Sanctum session, Player Tokens, and Integration Tokens (issued by an artisan command until the Admin UI exists). Verify with tests that ordinary users cannot create tokens and that each guard accepts only its own token kind.
- [ ] 5.2 API controllers and Resources for Parties, membership and roles, calling the Epic 2 Actions. Verify with feature tests per endpoint and with the OpenAPI drift and coverage tests passing.
- [ ] 5.3 API controllers and Resources for search, Requests, Votes, Ratings, approvals and Player control. Verify with feature tests per endpoint and a passing OpenAPI check.
- [ ] 5.4 Public `party.{code}` channel with Broadcast Resources (no member ids or secrets) and coalesced Queue broadcasts. Verify with payload snapshot tests, a no-secrets architecture test, and a test that a burst of Votes produces one broadcast.
- [ ] 5.5 Presence, per-member and Moderator channels with authorisation, replacing v2's `party.{party}` channel that authorised everyone. Verify with positive and negative authorisation tests for every channel.
- [ ] 5.6 Complete `asyncapi.yaml` for all channels and Player messages, with payload schemas checked against the Broadcast Resource output. Verify with a green reflection test.
- [ ] 5.7 Load test: simulated Pusher clients for 25 Parties × 500 Members (about 1,000 concurrent connections) against Reverb with Redis scaling enabled. Verify by committing the results and the tuned Horizon worker counts to `docs/research/`.

## 6. Guest UI & TV screen

- [x] 6.1 Capture v2 reference screenshots (login, home, Party page with Queue and now playing, search, played history, TV screen, at mobile and desktop widths, light and dark) into `docs/design/v2-reference/`, indexed in its `README.md`.
- [ ] 6.2 App layout, navigation, login and join pages in Vue with Inertia props. Verify by comparing desktop with the `docs/design/v2-reference/` screenshots (the pages must match), and checking mobile against the mobile-first layout requirement in the theming spec, and with Inertia feature tests for props.
- [ ] 6.3 Party page with now playing, Up Next (shown as locked) and the Queue with voting, live through Echo. Verify against the desktop screenshots and the mobile-first layout requirement, and with Vitest component tests using a mocked Echo.
- [ ] 6.4 Search and request flow, including rejection and Pending notifications on the per-member channel. Verify against the desktop screenshots and the mobile-first layout requirement and with Vitest and feature tests.
- [ ] 6.5 TV screen (anonymous, read-only, public channel only) with the QR code, targeting landscape screens of 1024px and wider. Verify against the desktop screenshots and with a test that it loads without authentication and subscribes to no private channels.
- [ ] 6.6 Host and Moderator pages: settings, Pending approvals, Bans and the Party Log viewer. Verify with feature tests for permissions and Vitest tests for the components.

## 7. Theming

- [ ] 7.1 Instance Theme model seeded from the v2 Tabler values and rendered as `:root` variables. Verify with tests that the rendered output contains only variable declarations and that the seeded values match the Foundation defaults.
- [ ] 7.2 Party Theme overrides (colour tokens, curated font, TV layout preset) with server-side validation. Verify with dataset tests that reject invalid colours, unknown fonts and any free CSS.
- [ ] 7.3 Logo and background image uploads (validated, stored on the configured disk). Verify with `Storage::fake` tests for type and size limits.
- [ ] 7.4 Colour Scheme preference (light, dark or system) per user, persisted and applied with `data-scheme`. Verify with a feature test for persistence and a Vitest test for the toggle.
- [ ] 7.5 WCAG AA contrast warnings when a theme is saved. Verify with unit tests on known passing and failing colour pairs.

## 8. Mod system (+ AI Request Review Mod)

- [ ] 8.1 Mod contract, `ModServiceProvider` registry, per-Party enablement and typed settings DTOs. Verify with tests using a fixture Mod for enable, disable and settings validation.
- [ ] 8.2 Extension points: Mod `RequestRule`s after the core rules, `ScoreModifier` in selection, `ScheduledAction` fan-out with `SubmitSystemRequest`, scoped domain event listeners, and Party Log entries for auto-actions. Verify with a fixture-Mod test per extension point, including a disabled Mod having no effect.
- [ ] 8.3 Decorations DTO (allow-listed variants, accent tokens) included in API Resources and broadcasts, with a shared schema in OpenAPI and AsyncAPI, and rendered by core Vue components. Verify with schema checks passing and Vitest render tests.
- [ ] 8.4 Build-time Vue slot registry (`resources/js/mods/`) with named slots in the guest and TV pages. Verify with a Vitest test that a fixture slot component renders.
- [ ] 8.5 Spike: map `laravel/ai` Classification `Score` onto Jev's rubric levels. If it is lossy, write an ADR and a thin Jev HTTP client behind the same driver interface. Verify by committing the findings and the ADR if one is needed.
- [ ] 8.6 AI Request Review Mod: a queued `RequestRule` on the `mods-ai` queue with OpenAI and Jev drivers, holding the Request as Pending, and a fallback verdict on timeout. Verify with `Classification::fake()` (or a faked Jev client) for accept, reject and timeout.
- [ ] 8.7 Extensibility check: fixture Mods mimicking trust score (Score Modifier) and Whamageddon (Scheduled action plus Decorations) work without core changes. Verify with tests only. These fixture Mods are not shipped as real Mods.

## 9. Admin

- [ ] 9.1 Admin gate and layout. Horizon, Pulse and Telescope are mounted behind the gate. Verify with tests that non-admins get 403 on each.
- [ ] 9.2 User management: list, suspend, roles, and starting act-as-Host. Verify with feature tests and Party Log assertions.
- [ ] 9.3 Social provider credentials (encrypted) and site settings (name, logos, favicon, ToS and privacy URLs, default party). Verify with tests for the encrypted cast and for login pages reading the settings.
- [ ] 9.4 Instance Theme editor with contrast warnings. Verify with feature tests for save and validation.
- [ ] 9.5 Integration Token issue, list and revoke UI, replacing the artisan-only flow. Verify with tests that only admins can issue tokens and that revoked tokens fail with 401.
- [ ] 9.6 Mods catalogue showing the registered Mods and their instance availability. Verify with feature tests.

## 10. Stats & Party Export

- [ ] 10.1 Live Stats projections in Redis, updated from domain events (top Tracks, top requesters, most up/downvoted, time played), excluding Fallback Requests and Removed or Rejected activity. Verify with projection tests over a scripted Party.
- [ ] 10.2 Live Stats UI for Members with real-time updates, and a documented broadcast. Verify with Vitest tests and a passing AsyncAPI check.
- [ ] 10.3 Versioned Party Export JSON for Ended Parties, available to the Host and admins at an `/api/v1` endpoint documented in OpenAPI. Verify with a schema snapshot test and access-control tests.
- [ ] 10.4 End-to-end check: a scripted Party with the Fake Provider and Fake Player runs from creation to Ended and Export, with the full suite, PHPStan, Pint, Rector and the spec checks all green. Verify with a green CI run on `feature/v3-rewrite`.

## Workflow follow-up

- Run `/no-comments` over the work before merging to `feature/v3-rewrite`.
- Archive the `v3-rewrite` change once all tasks are complete and reviewed.
