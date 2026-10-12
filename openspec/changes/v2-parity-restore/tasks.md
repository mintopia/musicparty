# Tasks

Each checkbox is about one PR, done in its own worktree off `feature/v3-rewrite`. Each task is a vertical slice. It covers schema, Action, web and API routes, Vue and tests together, and lands its own Pest or Vitest tests at the seams in design.md ("Test seams"). Each must pass PHPStan level 8, Pint and Rector. Tests run on in-memory SQLite with no other service (ADR-0024). While working, run only the affected tests; run the full suite before merging. Flaky tests block the merge.

Any change to an `/api` route, an API Resource, a channel or a broadcast updates the OpenAPI or AsyncAPI document in the same PR. Every new UI capability gets its `/api/v1` equivalent with the same authorisation.

UI tasks must match the v2 look (design D18). Each one names its references from `docs/design/v2-reference/` and `preview-screenshots/v2-full-*.png`, and attaches v3 screenshots at 1280px and 390px, light and dark.

`[id]` lists the triage finding ids (`feat-*`, `ui-*`, `diff-*` from round 2026-10-10-v2-parity, `dec-*` from 2026-10-11-gap-decisions) and the consolidated gap report work package (`WPn`). Group dependencies are in design.md under "Task group ordering". design.md records the user's answers under "Resolved questions"; the one remaining open question (Soloist's command name) is confirmed at the start of task 6.1.

## 1. Flash messages

- [ ] 1.1 [consolidated WP3] Use one flash convention (design D1). Replace every `->with('successMessage'|'errorMessage'|'infoMessage', ...)` under `app/` with `success`, `error` or `info`. Delete the `flash.successMessage` readers in `Party/Settings.vue`, `Members.vue`, `Mods.vue`, `Pending.vue` and `Blocklist.vue` so the shell's `FlashAlerts` shows them. Verify with:
  - an architecture test that fails on any old key in `app/` or any Vue read of a flash key outside `success|error|info|warning`
  - one feature test per area (party settings, members, mods, pending, blocklist, Spotify link, signup, logout) asserting `flash.success` or `flash.error` in the next Inertia response
  - a Vitest test that `FlashAlerts` renders each of the four kinds once
- [ ] 1.2 [consolidated WP3] Show flashes on the login page and restore v2 wording: "You have been logged out", "You have been logged in", "Your account has been created", "Your profile has been updated", "Unable to login", "Your account has been suspended". Render `FlashAlerts` in the login layout. Verify with feature tests that logout then GET `/login` shows the logged-out message, that a suspended login shows the suspended error, and that signup completion flashes the created message. Add a Vitest test for the login page rendering `info` and `success`.

## 2. Host setup and go-live (blocker)

- [ ] 2.1 [feat-55, consolidated WP1] User-scoped Spotify control linking and the Link Spotify Account page (design D2).
  - Replace `parties/{party}/spotify/link` with `GET spotify`, `GET spotify/link/{account}`, `GET spotify/link/return` and `DELETE spotify/link/{account}`, where `{account}` is `control` or `search`.
  - Add the `return` parameter, validated as a same-origin relative path.
  - Add the playback scopes and a `linked_accounts.scopes` column. An old control link without the playback scopes shows "Re-link needed for playback".
  - Host unlink pauses dependent Parties with a Party Log entry.
  - Build `pages/Spotify.vue` from v2 `users/spotify.blade.php`: control card, Create Party button. Link to it from the Party settings Playback section, from Create Party, and from a Party page warning shown to the Host when the Player needs a link that is missing.
  - Add API equivalents for link status and unlink.
  - Verify with feature tests for: the redirect asking for all six scopes; the callback storing scopes and returning to a valid `return`; an external `return` (`//evil.example`) falling back to `/spotify`; a non-Host user without create-party being refused; unlink pausing a Live Polling Player Party and writing the Party Log entry; an old link reported as needing re-link. Add a Vitest test for the page states, and v2 screenshot comparison.
- [ ] 2.2 [diff-8] Spotify Search account (design D3, ADR-0028).
  - Add the `spotifysearch` catalogue entry, seeder row and admin provider settings. It is never offered for login.
  - Add the Search card on the Link Spotify Account page, shown only when the provider is enabled.
  - Add a `SearchCredentials` resolver used by Spotify search: the Host's Search account if usable, else the Host's control account if usable, else instance client credentials. The Search link is optional everywhere.
  - Key the Spotify backoff per client application (amends ADR-0021).
  - A Search account rejected with `invalid_grant` is marked `needs_relink`, search falls back to the control account, and the Host sees a warning.
  - Add "Spotify Search account" to GLOSSARY.md.
  - Verify with `Http::fake` tests: search sends the Search account's bearer token when linked; it uses the control account's token when no Search account is linked or it needs re-linking; it uses client credentials when the Host has neither link; a 429 on the Search app does not block a poll made with the control app, and the reverse; `ListLoginProviders` never returns `spotifysearch`; admin provider settings list it.
- [ ] 2.3 [feat-48, feat-49, consolidated WP1] Fallback and History Playlist pickers on the settings page (Playback section). Each lists the Host's playlists through the existing playlists Action and accepts a pasted link, URI or id parsed by a `PlaylistReference` value object. Choosing none for History stops appending. The Fallback gate result (playable count against 20) shows inline after saving. Verify with:
  - `PlaylistReference` unit tests (URL with query string, `spotify:playlist:` URI, bare id, garbage rejected)
  - feature tests that saving through the web route changes the same setting the API changes and re-runs the gate
  - an end-to-end feature test with the Fake Music Provider: create a Party, link the control account, pick a Fallback Playlist with 25 playable Tracks, press Go Live through the web route; the Party is Live
- [ ] 2.4 [feat-40, diff-12, dec-6] Player kind and Polling Player device on the settings page (design D11, ADR-0032).
  - Add `parties.playback_device_id` and `playback_device_name`.
  - Add `SpotifyPlaybackClient::devices()`, plus `GET /api/v1/parties/{party}/player/devices` and its web counterpart.
  - `PUT parties/{party}/player` accepts `device_id`. Every Polling Player command passes `device_id` when set.
  - Spotify "Device not found" becomes a conflict error naming the device, plus a `player.device_missing` Party Log entry at most once a minute.
  - The settings Player select uses `ChangePartyPlayer`. Choosing Soloist shows a link to Player Tokens instead of the picker.
  - No force column, job or command.
  - Verify with `Http::fake` tests that play sends `device_id`, that a 404 device produces the conflict and one Party Log entry for two presses within a minute, and that the devices endpoint is Host-only. Add a scheduler test that nothing starts playback for an idle Party, and a Vitest test for the picker states (loading, list, re-link needed).

## 3. Party layout and navigation

- [ ] 3.1 [feat-2, ui-P5, consolidated WP2, WP11] Add `PartyLayout` and the shared `partyContext` prop (design D7). The prop holds the Party summary, now-playing from the shared presenter (plus `started_at`, `position_ms`, `paused`, `net_rating`), the viewer's role, the Policy-built `menu` and the Party Theme CSS. Every page under `parties/{code}` uses the layout. The layout owns the public-channel subscription for now-playing and Party state. Pages stop mounting the banner themselves. Verify with:
  - an Inertia feature test, for each Party route, that `partyContext` is present with the right role and the Party Theme CSS
  - a test that pages outside a Party have no `partyContext`
  - a Vitest test that a `party.state_changed` event updates the header on the Party Log page without a reload
  - a query-count assertion for the prop
- [ ] 3.2 [ui-Q1, ui-Q2, ui-Q3, ui-Q4, ui-Q10, ui-Q11] Rebuild `NowPlayingBanner` to v2's `components/Player.vue` layout:
  - blurred album-art backdrop with gradient
  - cover, Track, artists, requester
  - progress bar with elapsed and total time, ticking every second from `started_at` and `position_ms`, frozen when paused
  - net rating, and "There is nothing playing" when idle
  - icon Play/Pause and Next for the Host inside the banner, disabled when the Player does not support them; Previous is added in 6.1

  Remove the separate `PlaybackControls` stack. The Party name and code card renders only in the Party section. Verify with Vitest (fake timers) that elapsed advances each second and stops when paused, that the controls are disabled for unsupported Players and absent for Guests, and that the header card is absent on the Queue, Search and History sections. Compare against `party-queue-desktop-*.png` and `v2-full-*.png`.
- [ ] 3.3 [ui-X9] Mobile mini bar: add a progress bar, and make the expand control open the full now-playing view and return to the same scroll position. Verify with a Vitest test (open, close, scroll position kept) and a 390px screenshot against `party-queue-mobile-*.png`.
- [ ] 3.4 [feat-3, ui-H3, diff-16, ui-G7, feat-34, consolidated WP6] Party menu from `partyContext.menu` in `SidebarNav` and the mobile menu. Members get Queue, Search, History and TV Mode. Moderators add Users, Moderation, Pending and Party Log. The Host adds Settings, Theme, Player Tokens, and Web Player for a Browser Player Party. Theme and Pending are no longer orphaned. Mark the active entry and match v2's spacing and active marker. The sidebar's party list matches v2 (ui-H3). Verify with a feature test of the `menu` per role, a Vitest test that the TV Mode entry links to `/parties/{code}/tv` and that Web Player is hidden for non-Browser Parties, and a screenshot comparison of the sidebar against `v2-full-*.png`.
- [ ] 3.5 [ui-G1] Breadcrumbs end with the visible page name, and Party/Show uses the current `section` (Queue, Search, History, Party). Verify with a Vitest test of `pageFallback` for each section and a test that no breadcrumb shows a component name.
- [ ] 3.6 [ui-X8] The Browser Player page for a Party whose Player is not the Browser Player redirects to the Party page with an `info` flash. Verify with feature tests for a Polling Player Party (redirect) and a Browser Player Party (page renders).

## 4. Identity, signup and login

- [ ] 4.1 [dec-5] Unique nicknames (design D5). Add `users.nickname_key` with the dedupe migration (rename format per resolved question 8, audit each rename) and the unique index. Add a `UniqueNickname` rule and `max:32` in signup, profile and admin user edit. Set `Signup.vue` and `Profile/Edit.vue` to `maxlength=32`, with a hint on the rules. Verify with:
  - Form Request tests (duplicate in other case refused, own nickname in other case accepted, 33 characters refused)
  - a migration test on a fixture with "Alex" and "alex" (lower id keeps "Alex"; the other becomes "alex 2"; index created)
  - a test that two concurrent signups taking one nickname leave one with a validation error
- [ ] 4.2 [dec-4] Account merge by email on login (design D4, ADR-0027, resolved question 1: admins are merged too). Add `linked_accounts.email_normalised` with an index and backfill. Add `emailVerified()` per provider. Implement the merge in `ResolveSocialUser`. Add the `SocialAccountAutoLinked` event with an Admin audit listener, and the `info` flash. Verify with tests for:
  - same email, other provider: merged, audited, flashed
  - an explicit unverified flag: not merged
  - two users matching: new user and a warning log
  - suspended match: refused
  - Steam with no email: new user
  - Profile explicit linking: unchanged
  - the architecture test showing no Identity write to Admin models
- [ ] 4.3 [dec-9] Auto-join (design D6). The Party show route joins a logged-in, signed-up non-member as Guest under the `party-join` limiter, and falls back to the join form when limited. Anonymous visitors log in and return to the Party. The default-Party redirect joins. Update the membership spec scenario tests. Verify with feature tests for member (no duplicate), non-member (joined as Guest), unknown code (join form with error), anonymous (login then joined), rate limited (join form), Ended Party (joined, read-only), and default Party.
- [ ] 4.4 [consolidated WP4] Signup parity. Read the privacy policy URL from `SiteSettings`, not `config('app.privacy_url')`. Require consent when the terms or privacy URL is set, with the error naming the configured documents. The page follows v2 `users/signup.blade.php`: "Create Account", provider avatar, nickname hint, a "Terms and Conditions" and/or "Privacy Policy" label with links, and Cancel that logs out. Verify with Form Request tests for each URL combination and its error text, a feature test that Cancel logs out, and a screenshot comparison.
- [ ] 4.5 [consolidated WP4, ui-L1, ui-L2, ui-L3, ui-L4] Login page parity. Show the site name and the light/dark logo from site settings, with the site name in the copyright line. Add `min-w-0` and overflow handling so there is no horizontal scroll at any width. Provider buttons and the mobile layout follow v2. Verify with a feature test that the props carry the site name and logos, a Playwright check that `scrollWidth <= clientWidth` at 1280px and 390px with a cover image, and a comparison against `login-*.png`.
- [ ] 4.6 [dec-7, diff-10] One Host per Party (ADR-0031, membership delta "Party Roles"). Verify that the role-change Action, web route and API refuse `host` as a target role (422) and that a Party always has exactly one Host member, adding tests where missing. Correct any UI that offers a Host option. No other behaviour change.

## 5. Party settings and creation

- [ ] 5.1 [feat-6, feat-33, feat-42, feat-45, feat-46, ui-X3] Sectioned settings page (design D12) following v2 `parties/_form.blade.php`:
  - General: name
  - Playback: from 2.3 and 2.4
  - Requests and Restrictions: request rules, downvotes on/off, downvotes per hour, raffle (selection mode) with "Tracks with more upvotes are more likely to be played", approval, freeze while Paused
  - Display: from 5.3
  - Mods: link to the Mods page

  Each section saves through the same Actions as the API and flashes. The Settings and Theme pages share one card style. Verify with feature tests per section, including that a Moderator is refused and that a Party Log entry records old and new values. Add a Vitest test that the downvotes-per-hour field hides when downvotes are off, and a screenshot comparison with v2 edit.
- [ ] 5.2 [feat-5, ui-X7] Create Party form with the v2 fields (name, Provider, Player, downvotes, raffle, request rules) and the Spotify prerequisite. It refuses creation only when the chosen Player needs a control link that is missing, and links to `/spotify?return=/parties/create`. The Search link is never required, even when Spotify Search is enabled (resolved question 5). Verify with feature tests for each prerequisite case, including creation succeeding with Spotify Search enabled and no Search link, settings applied at creation, and the non-Spotify (Soloist) path needing no link, plus a screenshot comparison.
- [ ] 5.3 [feat-47, feat-34, dec-8] Add `parties.show_qrcode` (default true) in `UpdatePartyRequest`, the API Resource, the public Party payload (AsyncAPI updated) and the Display section. The TV hides the QR code and join link when it is off, live. Verify with feature tests (default on, toggle logged), an AsyncAPI coverage test, and a Vitest test that a `party.state_changed` payload with `show_qrcode: false` hides the QR code on `Tv.vue`.
- [ ] 5.4 [feat-41] Player Tokens page (`Party/PlayerTokens.vue`) for the Host: list label, last used and expiry; issue with a label, show the token once with copy; revoke with confirmation. It uses the existing API Actions through web routes. Verify with feature tests (Host only; token shown only in the issue response; revoke disconnects using the existing fake) and a Vitest test that the token is cleared from the page state on navigation.

## 6. Previous control

- [ ] 6.1 [dec-2] Previous control (design D8, open question 1, resolved question 3).
  - First confirm the Soloist protocol in `/home/workspace/musicparty/musicparty-soloist`: find where Soloist handles commands relayed from Music Party (its proxy relays `{type: "command", command: ...}` frames, `src/proxy.ts`) and whether it accepts `skip_prev`, which its own UI sends (`src/web-vue/pages/Landing.vue`, `components/MiniPlayer.vue`). Record the finding in the PR. Use the name Soloist understands; if it handles no relayed previous command, the Soloist Player declares Previous unsupported.
  - Add `Control::Previous`, `Player::previous()`, `previous` in the web and API route constraints, and OpenAPI.
  - Polling Player: seek 0 when position is at least 3 s, else Spotify previous on the chosen device. Soloist: the confirmed command. Browser: unsupported.
  - A replay records nothing: the coordinator's marker points now-playing back at the existing most recent Play, with no new Play, no Queue advance, no Up Next change, no History Playlist append and no no-repeat update.
  - Add the Previous icon in the banner.

  Verify with tests for: restart at 40 s (seek 0, no Play); previous at 1 s (Spotify previous called; Play count, History, Live Stats, Party Export and the no-repeat check unchanged; a Rating during the replay lands on the existing Play; Up Next unchanged and handed off after); Soloist command sent with the confirmed name; Browser refused; Moderator forbidden; a replay without a marker handled as today. Add a Vitest test that Previous is disabled when the Player does not support it.

## 7. Queue and moderation

- [ ] 7.1 [feat-9, ui-Q9, dec-10] Queue list (design D13). Add a client-side window of 20 with an IntersectionObserver that loads 20 more. Live updates keep the window size and scroll position. Show a remove action for the Host and Moderators on every Queued Request (the backend already allows it) and for the requester where allowed. Add tests documenting the exact duplicate rule: same Track id, same ISRC, and a similar name with a different id and ISRC is not a duplicate. Verify with a Vitest test (75 entries: 20 shown, sentinel loads to 40, a queue event reorders without shrinking the window), a feature test that a Moderator removal from the list route removes another Member's Request, and the duplicate tests.
- [ ] 7.2 [feat-30, consolidated WP7] Blocklist parity.
  - An edit form for value, type, regex flag and notes, using the existing PUT route. Edits are logged with old and new values.
  - Delete asks for confirmation.
  - Non-regex values are matched whole with `*` as a wildcard and every other character literal, ignoring case for names and ISRC (resolved question 7).
  - Regex is allowed on every match type.
  - Prefill from `?type=&value=&name=` query parameters for the song detail Moderate action.

  First confirm the current behaviour with tests, since this item was unverified in the status check. Verify with `Blocklist` unit tests for `*christmas*`, `AC/DC (live)` literal, a regex on ISRC, a case-sensitive Track id, and the existing regex-failure test; a feature test for edit logging; and a Vitest test that delete needs a confirm.
- [ ] 7.3 [ui-Q5, ui-Q6, ui-Q7, consolidated WP10] Queue page details:
  - a search box beside the Queue heading that opens the Search section with the query
  - "Upcoming Songs" heading and hint in weighted mode
  - filled vote icons for the Member's Vote; a heart when downvotes are disabled
  - centred vote errors
  - v2 row height and cover placeholder

  Verify with Vitest tests per state and a comparison against `party-queue-*.png`.
- [ ] 7.4 [consolidated WP10] Session keep-alive. While a Party page is visible, ping a lightweight authenticated endpoint (for example `GET /api/v1/ping` with the session) about every 60 seconds, paused when the page is hidden. Verify with a Vitest test (fake timers, visibility change pauses) and a feature test that the ping touches the session.
- [ ] 7.5 [ui-X4, ui-X5] The Pending page action row does not wrap at 1280px, and the Party Log shows a readable label for every event key the backend writes. Generate the list from the Party Log event enum or constants, and fail a test when a key has no label. Verify with that test and screenshots.

## 8. Search

- [ ] 8.1 [feat-10, consolidated WP9] Paged search and result details (design D14). `SearchPartyProvider` takes `page` (20 per page) and returns `has_more`. Track metadata gains `popularity`. Results show 0 to 5 stars, View on Spotify, and a Next/Load more control. Verify with `Http::fake` tests that page 2 sends offset 20 and that `has_more` is false on the last page, a Fake Provider contract test for popularity, and a Vitest test for stars and paging.
- [ ] 8.2 [feat-10, ui-S3, consolidated WP9] Eligibility and voting from results. Add `Queue\Actions\AssessRequestEligibility` for a batch of Tracks: core rules plus Mod rules marked cheap, with the Blocklist loaded once. Each result returns `eligible`, `reason`, `queued_request_id` and `my_vote`. Ineligible results show a disabled button with the reason. Queued results show up, down and retract Vote controls. Add API equivalents. Verify with tests: a too-long Track shows the reason; a queued Track returns its request id and the Member's Vote; AI Request Review is not called during search; a constant query count for 20 results; and a Vitest test for voting from a result.
- [ ] 8.3 [ui-S2, ui-S5] Search input styling and the empty-state wording follow v2 (confirm the exact string in v2 `parties/search.blade.php`). Verify with a Vitest snapshot of the empty state and a comparison against `party-search-*.png`.

## 9. Songs, history and members

- [ ] 9.1 [feat-18, diff-9, ui-P1, ui-P2, ui-P3, ui-P4, consolidated WP8] History browsing (design D15):
  - sort by name, artist, requested, played or score, with direction
  - `per_page` of 10, 20, 50 or 100
  - type `all|played|unsent`, defaulting to `all`
  - Track names link to song detail
  - custom selects instead of native ones
  - no table overflow at 1280px
  - compact mobile rows

  Verify with feature tests for each sort and its stability across pages, unsent listing, and a constant query count. Add Vitest tests for the controls and comparisons against `played-history-*.png`.
- [ ] 9.2 [feat-19, feat-20, feat-21, feat-22, feat-23, feat-24] Song detail page and API. Show the Track, requester, requested and sent times, Votes by Member, Ratings by Member, other appearances by Track id or ISRC, View on Spotify, and Moderate (Host and Moderators, prefilling the Blocklist from 7.2). Verify with feature tests for Member access, non-member refusal, Guest without Moderate, a constant query count, and the API payload. Add a Vitest test for the Moderate link and a comparison with v2 `upcomingsongs/show.blade.php`.
- [ ] 9.3 [feat-25, ui-X6] Members list: search by nickname, role filter, sort by nickname, role or joined, paging, Joined and Ban columns, a custom role select, and the `members.columns` slot (used by 10.1). API filters match. Verify with feature tests per filter and sort, Guest refusal, a constant query count, and a Vitest test for the role select.
- [ ] 9.4 [feat-26] Member detail page and API: role, Ban status, joined time, Requests, and Votes (searchable by Track, paged, newest first), with Ban and role actions under the existing rules. Verify with feature tests for Moderator access, another Party's Member refused, vote search, and paging.

## 10. Mods

- [x] 10.1 [diff-1] Trust Score Mod (design D9, ADR-0030, resolved question 4).
  - Add the `mod_trust_scores` table, the hourly Scheduled action and personalised PageRank.
  - Settings: anchor (default Host), window (7 days), weighting (off) and maximum bonus (2).
  - The weighting Score Modifier.
  - A `party:calculatetrust {code}` command that prints a nickname and score table.
  - A trust column through `members.columns` for the Host and Moderators.
  - Add the Mod to GLOSSARY.md.

  Verify with PageRank unit tests on hand-computed three- and four-member graphs; a test that the anchor's upvotes raise A over B; a weighting on/off Score test; that a Guest and the public API see no trust; that a run is logged; and the command output.
- [ ] 10.2 [dec-3, diff-3] Whamageddon Mod (design D10, resolved question 6).
  - Add the `mod_whamageddon_states` table and settings (Track, label, bonus 1, re-add 2 h, quiet hours 02:00 to 10:00, hold from 23:00).
  - Enable adds a system Request with a Decoration badge; disable removes it.
  - The hourly action adds the bonus through a Score Modifier, re-adds after play, and holds and releases through a new `Queue\Actions\SetRequestNotBefore`.
  - Add the Mod to GLOSSARY.md.

  Verify with frozen-clock tests for: enable; hourly bonus; no bonus at 03:00; hold at 23:30 until 10:00; release at 10:05; re-add 2 h after play; disable removing it; nothing while Paused; a Blocklisted Track logged once per run; and the architecture test showing no Mod writes to Queue models.

## 11. Operator tools

- [ ] 11.1 [dec-11, diff-17] `party:*` commands and log mirroring (design D16): `party:check {code?}`, `party:fallback`, `party:updatestate {code}`, `party:pushupdate {code}`, `party:refreshaccesstokens`, with `party:calculatetrust` from 10.1 using the same base. Add a `MirrorsLogToConsole` trait mapping verbosity, and a `PartyCommand` base resolving codes case-insensitively. Document the commands in the README operator section. Verify with:
  - `artisan()` tests per command (output and exit codes, unknown code non-zero)
  - a test that `party:check -v` shows an info log line
  - `ConsoleLoggingTest` still green
  - a test that no `party:force` command is registered
- [ ] 11.2 [dec-11, diff-17] Proactive Host token refresh (design D17). Schedule `RefreshExpiringHostTokens` every minute with `onOneServer`, covering control and Search accounts expiring within 5 minutes, through `HostAccountTokens` and its lock. Verify with frozen-clock `Http::fake` tests that a token expiring in 3 minutes is refreshed once even with a concurrent on-demand refresh, that `invalid_grant` marks `needs_relink`, and that `schedule:list` includes the job.

## 12. Theming and visual parity

- [ ] 12.1 [ui-G9] Deduplicate `Icon.vue` (`thumbUp` and `thumbDown` defined twice) and restore v2's icons where v3 differs (music-search, playlist-add, brand-spotify, heart, previous). Verify with a unit test that the icon map has unique keys and every icon used in `resources/js` exists.
- [ ] 12.2 [ui-T2, ui-T3] TV screen details following v2 TV: font, icons and progress bar. The TV is usable on a phone, with no horizontal scroll at 390px and readable now playing. Verify with a comparison against `tv-*.png` and a Playwright overflow check at 390px.
- [ ] 12.3 [ui-H2, ui-X1, ui-X2, dec-1] Visual alignment: the Home profile card typography follows v2; the remaining native controls on admin pages (`Admin/Theme.vue` select first, then every admin page checked) are replaced with the shared custom select; the Admin Users page is checked at 390px. Add a test that the admin Theme page has no theme list or activation route (theming delta "No theme library", ADR-0026). Verify with screenshots against `home-*.png` and `v2-full-*.png` and that test.

## 13. Admin API parity

- [ ] 13.1 [consolidated WP12] The `/api/v1/admin` endpoints match the admin UI (api spec "Capability parity"): user show, update (nickname, name, terms, signup flag), delete, linked-account delete, the Mods catalogue, and Spotify Search and Laravel Passport provider settings. Integration Token abilities are enforced. First list which are missing, since this was unverified in the status check. Verify with feature tests per endpoint (admin token allowed, missing ability 403, non-admin 403) and the OpenAPI coverage test.

## 14. Integration

- [ ] 14.1 Run the full Pest suite, PHPStan, Pint, Rector, Vitest, `npm run build`, the OpenAPI and AsyncAPI coverage tests and the spec lint on `feature/v3-rewrite`. Run `/no-comments` over the change. Do a final side-by-side pass of every v2 reference screenshot against v3 at the same width and scheme. Verify with a green CI run and the screenshot set attached to the final PR.

## Coverage

Every Partial or Open item from the 2026-10-12 status check maps to one task. Items marked "covered by" need no further code.

| Item (status) | Task |
|---|---|
| ui-G1 (Partial) | 3.5 |
| diff-16 (Partial) | 3.4 |
| ui-G7 (Partial) | 3.4 |
| ui-H2 (Partial) | 12.3 |
| ui-X1 (Partial) | 12.3 |
| ui-X3 (Partial) | 5.1 |
| ui-X4, ui-X5 (Partial) | 7.5 |
| ui-P1, ui-P4 (Partial) | 9.1 |
| ui-S2 (Partial) | 8.3 |
| feat-9 (Partial) | 7.1 |
| feat-30 (Partial) | 7.2 |
| feat-18, diff-9 (Partial) | 9.1 |
| ui-T2, ui-T3 (Partial) | 12.2 |
| ui-Q7 (Partial) | 7.3 |
| feat-19 to feat-24 | 9.2 |
| feat-25 | 9.3 |
| feat-26 | 9.4 |
| feat-40, diff-12, dec-6 | 2.4 (no force: players delta "No forced playback", ADR-0032) |
| feat-41 | 5.4 |
| feat-47, feat-34, dec-8 | 5.3 (TV link in 3.4) |
| feat-55 | 2.1 |
| feat-2, ui-P5 | 3.1 |
| feat-3, ui-H3 | 3.4 |
| feat-5, ui-X7 | 5.2 |
| feat-6, feat-33, feat-42, feat-45, feat-46 | 5.1 |
| feat-48, feat-49 | 2.3 |
| ui-X6 | 9.3 |
| feat-10, ui-S3 | 8.1, 8.2 (split: paging and details / eligibility and voting) |
| ui-S5 | 8.3 |
| ui-Q1, ui-Q2, ui-Q3, ui-Q4, ui-Q10, ui-Q11 | 3.2 |
| ui-Q5, ui-Q6 | 7.3 |
| ui-Q9, dec-10 | 7.1 |
| ui-P2, ui-P3 | 9.1 |
| ui-X8 | 3.6 |
| ui-X9 | 3.3 |
| ui-G9 | 12.1 |
| ui-L1, ui-L3, ui-L4 (and ui-L2 visual check) | 4.5 |
| dec-1 | ADR-0026 and theming delta; check in 12.3 |
| dec-2 | 6.1 |
| dec-3, diff-3 | 10.2 |
| diff-1 | 10.1 |
| dec-4 | 4.2 |
| dec-5 | 4.1 |
| dec-7, diff-10 | ADR-0031 and membership delta; check in 4.6 |
| dec-9 | 4.3 |
| dec-11, diff-17 | 11.1, 11.2 |
| diff-8 | 2.2 (page in 2.1) |
| WP3 flash convention | 1.1 |
| WP3 login flashes and v2 texts | 1.2 |
| WP4 privacy URL and consent | 4.4 |
| WP4 signup page | 4.4 |
| WP4 login site name and logo | 4.5 |
| WP1 scopes and Host unlink | 2.1 |
| WP1 go-live blocked | 2.3 |
| WP7 wildcard and regex on identifiers | 7.2 |
| WP8 history sort and per-page | 9.1 |
| WP9 eligibility and voting | 8.2 |
| WP10 keep-alive | 7.4 |
| WP10 heading, heart, centred error | 7.3 |
| WP11 Party Theme on guest pages | 3.1 |
| WP12 Admin API parity | 13.1 |
| WP6 Theme and Pending orphaned, TV link | 3.4 |
| ui-X2 visual check (Done, check pending) | 12.3 |
| feat-80, diff-13 Passport (Done) | ADR-0029, membership and admin deltas; no code |
| WP12 Pulse resolver (Done) | none |

## Workflow follow-up

- After the user approves, run `/to-tickets` on this change. Groups map to the `blocked_by` edges in design.md.
- When every task is merged, sync the delta specs into `openspec/specs/` (including the new `web-ui` capability) and archive `v2-parity-restore`.
