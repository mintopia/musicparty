# Built UI vs approved mockups (#438)

Gallery: review/gallery.html (mockup left, built right). Built captures: review/built/. Seed/harness gaps mean some frames are 'not reproducible'. Shared-CSS, copy, heading, error-page fixes were made in #438; the rest are filed as follow-ups under #414 (see FOLLOWUPS section).

---

# Review harness (#450)

Review-only fixtures live in `database/seeders/Review/ReviewHarnessSeeder.php`, run with `php artisan review:seed` (refuses to run in production, not called by `DatabaseSeeder`, safe to re-run). Dev stack: `docker compose -p <project> up -d`, then `docker compose -p <project> exec --user "$(id -u):$(id -g)" musicparty php artisan review:seed`. `docker-compose.yml` already has `reverb`, `horizon` and `scheduler` services, and websockets reach Reverb through the app's own hostname (ADR 0004). `.env` needs `BROADCAST_CONNECTION=reverb` and `REVERB_*`, and `REVERB_ALLOWED_ORIGINS` must include the host the browser uses (for example `localhost` when capturing against a published port), otherwise Reverb rejects the socket with "Origin not allowed" and pages show the Reconnecting pill. Set `DEBUGBAR_ENABLED=false` for clean captures. Branded 403/404/feature-off pages only render with `APP_DEBUG=false`.

Seeded states (all keyed by stable code or nickname; every review user has `first_login` off so a minted session lands on the page, not signup):

| Gap | Fixture |
| --- | --- |
| Themes | StratLAN (active, dark, 7 tokens, custom CSS), Reference Theme, Disposable Theme (non-built-in, inactive; StratLAN is also deletable) |
| Branding | `logo-light`, `logo-dark`, `cover-image`, `favicon` set to placeholder URLs |
| Providers | Discord on (sign-in), Spotify on for linking only, Steam on for sign-in with an empty API key |
| Spotify relink | user `review-relink` (needs_relink_at set on its Spotify account) owns party `RLNK` (provider spotify) |
| Ready to host / unlink | `review-admin` (admin + create-party) and `review-both-spotify` have Discord + Spotify + Spotify Search; `review-two-links` has Discord + Steam |
| Flagged-not-held | party `RFLG`, `flag_requests` off; `review-member` is a plain member (Requests 403 frame) |
| AI empty | party `REMP`, AI Request Review enabled, zero reviews |
| AI answers | party `RAIS`, five reviews with `suitability` and `criterion` answers, including a probability for every criterion |
| Feature off | party `RNOA`, AI Request Review mod not enabled; `review-moderator` is its moderator |
| No device | party `RNDV`, owner `review-no-device`; fake provider scenario `no_device` (no devices) |
| Outside party | party `ROUT`, owner `review-outside`; fake provider scenario `outside` (playing from "Late Night Drive") |
| Admin all-clear | `php artisan review:seed --all-clear` clears the re-link and missing-secret fixtures; the seeder writes the scheduler heartbeat; Horizon, Reverb, Redis and DB come from the running stack |
| Admin no-parties | `php artisan review:seed --quiet-parties` marks every party inactive (the seeded parties and any others) |

Party codes are four letters because the join/invite flow only accepts `/parties/[A-Za-z]{4}`.

No-device and outside-party: the dashboard derives them from the live provider (`devices()`, `currentPlayback()`). The seeder gives the `fake` provider a per-owner scenario (cache key `FakeMusicProvider::scenarioKey(userId)`, ignored in production). Still unreproducible by design: e500 (needs a throwing route), web-player frames (Spotify SDK), `mods-ai-error` (the mockup shows an inline range error the settings form does not have), `admin-mods-empty` (three mods ship with the app).

Captures log in without OAuth by minting a session for a review user with tinker (store the user id under the web guard in a fresh session, then set the encrypted `music_party_session` cookie), and drive headless Chrome with Playwright. Light frames use the Default theme; switch the active theme for StratLAN frames.

Recapture status: every seed-gap frame was recaptured into `review/built/` against this harness (rows marked "Recaptured (#450)" below; the per-group totals further down predate the recapture). Reverb runs in the review stack and the recaptured frames are connected (no Reconnecting pill), so older notes below about missing Reverb, the WebSocket 502 and the no-device/outside/relink states being unreproducible describe the pre-#450 stack. Still not reproducible: web-player frames, e500, `mods-ai-error` (app has no inline range error) and `admin-mods-empty` (mods ship with the app). Found while recapturing and fixed here: `ReverbStatus` reported Reverb down whenever no channel was occupied. Found and not fixed (app follow-ups): Create Party done card clips on phone; Create Party with the Spotify provider 500s on an invalid token instead of showing an error; Mods confidence field accepts 150 with no inline error.

---

# Group 10-dashboard / 11-dashboard-phone / 12-queue

Built screenshots: /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/<frame>.png
Side-by-side (mockup left, built right): /tmp/task1349/pairs/<frame>.png
Scripts: /tmp/task1349/g1349.mjs, g1349b.mjs, g1349d.mjs, g1349e.mjs

Capture notes
- The Queue page is /parties/CODE (Parties/Show.vue + Upcoming.vue), NOT /parties/CODE/songs (that is the Requests page, group 20). The earlier interrupted run captured 12-* from /songs; all 12-* were recaptured from /parties/CODE.
- Shared party PLAY was being mutated by other reviewers (queue emptied, state flipped), so I cloned PAUS into a private party code MYQ ("Friday LAN Night", /tmp/task1349/clone_party.php) and drove its state with state.php. MYQ is a clone: no AI reviews, so "AI review" links in the flagged rows do not render (clone artifact, not an app bug; on PLAY they render). MYQ was left in the DB for re-use; delete with Party::whereCode('MYQ')->delete() if unwanted.
- Built uses fake provider: names (Neon Harbour, Olive Owner) differ from mockup content; judged on structure, not data.
- Pre-existing harness bug: state.php setState('..','playing') throws MassAssignmentException after a 'nothing' state (history deleted); run with Model::unguard() first.

## Frame table

| frame | verdict | reason | built screenshot |
|---|---|---|---|
| 10-dashboard-playing-party-desktop | deviates | No "Dashboard - Party - Join code" line in the band; no Up Next deck (right panel is Start Playback with a "Will play" row instead; Start Playback panel still shown while Playing); Upcoming rows have no 1..n numbering gutter, no rounded vote capsule, calendar-clock/trash replaced by plain clock and red X; no "About HH:MM" est start / "Plays at" pill on desktop rows; Copy join link is filled primary (mockup: outlined) and no "Guests join at /parties/CODE" URL; round-1 big headline present, status wording/pill OK ("Playing", pill "Playing") | built/10-dashboard-playing-party-desktop.png |
| 10-dashboard-playing-dark-desktop | deviates | Same structural gaps as light; dark tokens apply (surface, text) | built/10-dashboard-playing-dark-desktop.png |
| 10-dashboard-paused-party-desktop | deviates | Same as Playing; Paused headline + amber pill consistent (one wording) | built/10-dashboard-paused-party-desktop.png |
| 10-dashboard-nothing-playing-desktop | deviates | Headline/pill/next-step OK; Start Playback shows device picker + Will play like mockup; missing band line, Up Next, join URL, outlined Copy link; Needs attention lists flagged first (mockup all-clear n/a with flags) | built/10-dashboard-nothing-playing-desktop.png |
| 10-dashboard-nothing-playing-dark-desktop | deviates | As above, dark | built/10-dashboard-nothing-playing-dark-desktop.png |
| 10-dashboard-moderator-desktop | deviates | Moderator sees the lock card "Only Olive Owner can start playback" (matches). Flagged rows: icon-only green check / red X squares, no "Approve"/"Reject" words, no reason line ("Troll pick, 91% confident"), heading "3 Flagged Requests" not "...waiting for approval", footer "Flagged page" not "Open Flagged Requests"; Quick links Copy join link filled | built/10-dashboard-moderator-desktop.png |
| 10-dashboard-flagged-requests-desktop | deviates | Same flagged-row gaps as moderator frame | built/10-dashboard-flagged-requests-desktop.png |
| 10-dashboard-schedule-picker-desktop | deviates | Desktop Schedule control is a link that navigates to the Request detail page with a datetime-local field ("Send at (party time)", Save schedule / Clear schedule). No labelled calendar dialog, no quick choices, no "Plays at 21:50 today, after X" sentence. ScheduleSheet exists but only reached from the phone overflow menu | built/10-dashboard-schedule-picker-desktop.png (shows request detail page) |
| 10-dashboard-delete-dialog-desktop | matches | Modal text and Cancel / Delete request buttons identical ("Wildfire by Kestrel, requested by Tariq, will leave the queue and its 5 votes are lost."); backdrop blur; row icon differs (X vs trash) | built/10-dashboard-delete-dialog-desktop.png |
| 10-dashboard-player-stale-desktop | matches (content) | Reproduced by ageing playback_received_at 3 min: headline "Player stale", "Last heard: ... 3 minutes ago.", "3 minutes ago - stale" + Open Web Player link, Needs attention "Player stale ... Open Web Player" button, pill red. Layout gaps as in Playing frame | built/10-dashboard-player-stale-desktop.png |
| 10-dashboard-no-device-desktop | matches | Recaptured (#450) on RNDV: "No device" hero, Refresh devices, Needs-attention row | built/10-dashboard-no-device-desktop.png |
| 10-dashboard-outside-party-desktop | matches | Recaptured (#450) on ROUT: "Outside the party", Late Night Drive copy, Start the party | built/10-dashboard-outside-party-desktop.png |
| 10-dashboard-relink-needed-desktop | matches | Recaptured (#450) on RLNK: "Re-link needed", Re-link Spotify button and attention row | built/10-dashboard-relink-needed-desktop.png |
| 11-dashboard-phone-playing-party-phone | deviates | Stacked rows, title/artist full width, votes/length beneath, overflow menu, no horizontal overflow at 390 (matches). Gaps: no Up Next panel, Start Playback panel remains, no "Plays at" pill visible on row (pill is on list row only after scroll), join URL line missing, Copy join link filled | built/11-dashboard-phone-playing-party-phone.png |
| 11-dashboard-phone-playing-party-phone-dark | deviates | As light | built/11-dashboard-phone-playing-party-phone-dark.png |
| 11-dashboard-phone-playing-party-desktop-row | deviates | Same as 10-dashboard-playing-party-desktop (frame is the 1440 reference row) | built/11-dashboard-phone-playing-party-desktop-row.png |
| 11-dashboard-phone-nothing-playing-phone | deviates | Headline wraps ("Nothing / playing"), otherwise like mockup; device picker, Will play, Start button present; Up Next, band line missing | built/11-dashboard-phone-nothing-playing-phone.png |
| 11-dashboard-phone-nothing-playing-phone-dark | deviates | As light | built/11-dashboard-phone-nothing-playing-phone-dark.png |
| 11-dashboard-phone-moderator-phone | deviates | Same flagged-row gaps (no Approve/Reject words, no reason line) | built/11-dashboard-phone-moderator-phone.png |
| 11-dashboard-phone-overflow-menu-phone | matches | Menu items "Schedule - Pick a day and time for it to play" and "Remove request - It leaves the Queue and its votes are lost", 44px, labelled. Minor: menu clips at the right screen edge; "Skip to content" link visible over the page after tap | built/11-dashboard-phone-overflow-menu-phone.png |
| 11-dashboard-phone-schedule-sheet-phone | matches | Bottom sheet with calendar, time, quick choices, sentence, Schedule for HH:MM / Cancel | built/11-dashboard-phone-schedule-sheet-phone.png |
| 11-dashboard-phone-delete-confirm-phone | matches | Built modal full width at 390 (dialog verified on desktop frame; same component) | built/11-dashboard-phone-delete-confirm-phone.png |
| 12-queue-guest-default-desktop | deviates | Rows match (numbered, one line artist + requester, You badge + tint on own row, rounded capsule on own row only, Requested by you). Gaps: player band says "Fallback Track" (PartyPlayer.vue:132) instead of Fallback Playlist credit; band shows rate-up/down but no Requested by; a skeleton loader card stays below the list (see Deviations); band h2 precedes page h1 | built/12-queue-guest-default-desktop.png |
| 12-queue-guest-default-desktop-dark | deviates | As light; "You" badge contrast 3.12 | built/12-queue-guest-default-desktop-dark.png |
| 12-queue-guest-default-phone | deviates | Layout matches (votes under title, 44px). Same skeleton + Fallback Track gaps | built/12-queue-guest-default-phone.png |
| 12-queue-guest-default-phone-dark | deviates | As light | built/12-queue-guest-default-phone-dark.png |
| 12-queue-like-mode-desktop | matches | Hearts with count via shared VoteControl when downvotes disabled (captured by setting downvotes=false on MYQ and restoring). Skeleton gap as above | built/12-queue-like-mode-desktop.png |
| 12-queue-like-mode-phone | matches | Same on phone | built/12-queue-like-mode-phone.png |
| 12-queue-owner-default-desktop | deviates | Manager rows show only a trash icon after a divider; no calendar-clock Schedule action and no "Plays at" pill for a Scheduled Request (mockup shows both) | built/12-queue-owner-default-desktop.png |
| 12-queue-owner-overflow-phone | deviates | Menu has only "Remove from Queue - Guests can request it again"; mockup also has "Change schedule" and "View Request" | built/12-queue-owner-overflow-phone.png |
| 12-queue-owner-schedule-picker-desktop | missing | No schedule control exists on the Queue page (only a read-only "Scheduled for ..." line); no picker to capture | built/12-queue-owner-schedule-picker-desktop.png (plain queue, no picker) |
| 12-queue-queue-empty-desktop | matches | "The Queue is empty", description, single filled "Search for a track" | built/12-queue-queue-empty-desktop.png |
| 12-queue-queue-failed-desktop | matches | Forced by failing /api/v1/parties/CODE/upcomingsongs: cloud-off card "The Queue didn't load" + Retry (card appears after the scroll triggers the loader) | built/12-queue-queue-failed-desktop.png |
| 12-queue-queue-failed-phone | matches | Same on phone | built/12-queue-queue-failed-phone.png |
| 12-queue-undo-desktop | matches | Toast "Removed <b>Wildfire</b> from the Queue." Undo + close, fixed top right (per mockup note); DELETE mocked to avoid data loss | built/12-queue-undo-desktop.png |
| 12-queue-undo-phone | matches | Same on phone | built/12-queue-undo-phone.png |
| 12-queue-remove-failed-desktop | matches | Forced DELETE 500: red toast "Couldn't remove X. It is back in the Queue." with Retry; row restored with red left edge | built/12-queue-remove-failed-desktop.png |
| 12-queue-remove-failed-phone | matches | Same on phone (toast at top, red edge on row) | built/12-queue-remove-failed-phone.png |

Counts: 38 frames. matches 14 (incl. stale content), deviates 18, missing 1, not reproducible 3 (no-device, outside-party, relink-needed), plus 2 reproducible-with-caveat counted under matches. Also not reproducible in any frame: Reverb realtime (built shows "Reconnecting..." pill; WebSocket 502 console error on guest queue because no Reverb in this stack).

## Ticket criteria

#418 One playback status (10-dashboard)
- [x] One derived status drives headline, pill, Start Playback badge, Needs attention (same word in all four for Playing, Paused, Nothing playing, Player stale)
- [x] Next step as actions when not playing (Open Web Player, Start on Olive Owner's Spotify)
- [x] Ages in words ("3 minutes ago - stale", "2 seconds ago"); no raw minutes seen
- [x] Polling/device details in collapsible "Diagnostics" (summary)
- [ ] Live update and per-state tests not verified here (no Reverb; tests not run per instructions)
- [ ] Mockup match: partial, see frame table (Up Next deck, band line, join URL missing)

#419 Phone dashboard (11-dashboard-phone)
- [x] Below tablet each Upcoming row: full title/artist, time/votes beneath
- [x] Schedule/Remove in labelled overflow menu on phone
- [x] No horizontal overflow at 390 (audit: scrollWidth 390 on Dashboard)
- [x] Desktop layout unchanged
- [ ] Mockup match partial (Up Next, Plays-at pill placement)
- Search at 390 overflow: not in this group (see 13-search report)

#420 Guest queue voting (12-queue)
- [x] Queue uses shared VoteControl (hearts in like mode, arrows otherwise)
- [x] Vote targets >= 44px (audit lists no vote buttons under 44)
- [x] Own Requests marked "You" with tint, "Requested by you"
- [ ] Fallback Playlist credit: rows read "From the party's Fallback Playlist" (seen on the previous PLAY data) but the player band still reads "Fallback Track" (PartyPlayer.vue:132, also TrackRow.vue:132 default, TvPlayer.vue:138, Search/SearchTopResult.vue:70, Songs/Show.vue:170)

#421 Safe remove (12-queue)
- [x] Remove separated from votes by divider and spacing
- [x] Undo toast names the track and restores
- [x] Failed remove: danger toast + row returns
- [x] Load failure card with Retry
- [x] Remove hidden for non-managers (guest rows have no trash)
- [ ] Phone: Schedule/View items absent from overflow menu (menu only has Remove)

#417 Vocabulary (touches these pages): Dashboard says "Fallback Playlist" nowhere on the Dashboard rows except "Fallback Playlist" credit in Upcoming; "Flagged Requests" used; "Moderator" used; Quick link text fine. "Fallback Track" survives in the player band (see above).

#425 Heading levels: Dashboard h1 (visually hidden) OK, no skips. Queue page: first heading is h2 (player band title) before the h1 "Queue" (audit "first heading is h2, not h1") on /parties/CODE for guest and owner.

## Audit (390x844, MYQ, scripts /tmp/task1349/audit.mjs)

| page | overflow | headings | broken img | console | touch<44 | contrast |
|---|---|---|---|---|---|---|
| dashboard owner light | none | ok (h1 x1) | none | none | 13 (see list) | ok |
| dashboard owner dark | none | ok | none | none | 13 | ok |
| dashboard moderator | none | ok | none | none | 11 | ok |
| queue guest light | none | FAIL first heading h2 | none | WS 502 (no Reverb, env) | 11 | FAIL .badge ("You") 3.88:1 |
| queue guest dark | none | FAIL first heading h2 | none | none | 11 | FAIL .badge 3.12:1 |
| queue owner light | none | FAIL first heading h2 | none | none | 11 | ok |

Touch targets under 44: Skip to content (134x36), navbar-toggler 32x32, brand link, "Home" breadcrumb 39x17, user menu 32x32, "Show full path" breadcrumb toggle 32x44, "Open Web Player" fix link 105x15, Diagnostics summary 300x18, "Manage all N requests" 176x23, "Flagged page" 102x16, footer links, player band rate-up/rate-down 32x32 (queue), queue search input 40px and Search button 40px.
Wording: ages in words everywhere seen; state pill/wording consistent across headline, Start Playback pill and Needs attention; party URL: dashboard shows no /parties/CODE join URL (mockup shows "Guests join at /parties/KXQB").

## Deviations (severity, fix location, evidence)

1. High - Owner Queue lacks scheduling: no Schedule action, no "Plays at" pill, phone overflow lacks "Change schedule"/"View Request". Fix: resources/js/components/Upcoming.vue (reuse Dashboard/ScheduleSheet.vue + ScheduleRequestControl). Evidence: pairs/12-queue-owner-default-desktop.png, 12-queue-owner-overflow-phone.png.
2. High - Dashboard Up Next deck missing and Start Playback panel always shown; band lacks "Dashboard - Party - Join code" line. Fix: resources/js/components/Dashboard/ControlRoom.vue, UpNextDeck.vue, StartPlaybackPanel.vue, pages/Parties/Dashboard.vue. Evidence: pairs/10-dashboard-playing-party-desktop.png.
3. High - Flagged rows on Dashboard use icon-only Approve/Reject, no reason line, "Flagged page" footer; mockup has labelled 44px outlined Approve/Reject, "who + why" line, "Open Flagged Requests". Fix: resources/js/components/Dashboard/NeedsAttention.vue. Evidence: pairs/10-dashboard-flagged-requests-desktop.png, 11-dashboard-phone-moderator-phone.png.
4. Medium - Desktop Schedule on Dashboard navigates to the Request detail page (datetime-local) instead of the calendar dialog. Fix: Dashboard/UpcomingList.vue (open ScheduleSheet as dialog on desktop). Evidence: pairs/10-dashboard-schedule-picker-desktop.png.
5. Medium - Queue shows a permanent skeleton card under a fully loaded short list until the user scrolls (VueEternalLoading never resolves to no-more without a trigger). Fix: Upcoming.vue getPage / #loading slot (check sentinel/ initial page size). Evidence: probe: 8 rows + 1 skeleton at 0s, 3s, 6s; cleared only after wheel scroll; visible in every populated queue frame.
6. Medium - "Fallback Track" still shown in player band and shared components (violates #420/#417; GLOSSARY Fallback Playlist). Fix: PartyPlayer.vue:132, TrackRow.vue:132, TvPlayer.vue:138, Search/SearchTopResult.vue:70, pages/Parties/Songs/Show.vue:170 -> "From the party's Fallback Playlist". Evidence: pairs/12-queue-guest-default-desktop.png.
7. Medium - Queue page heading order: player band h2 precedes h1 "Queue" (#425). Fix: PartyPlayer.vue (h2 -> p/div, or page h1 before band). Evidence: audit.
8. Medium - "You" badge contrast 3.88:1 light / 3.12:1 dark (< 4.5). Fix: Upcoming.vue/TrackRow.vue badge colours (use text-bg-primary or darker text). Evidence: audit contrast.
9. Low - Dashboard Upcoming rows: no numbering, plain clock/X icons (mockup calendar-clock/trash), no rounded vote capsule, no est-start/"Plays at" pill on desktop, Copy join link filled primary not outlined, no join URL line. Fix: Dashboard/UpcomingList.vue, QuickLinks.vue. Evidence: pairs/10-dashboard-playing-party-desktop.png.
10. Low - Phone: overflow menu clips at right edge; "Skip to content" link stays visible over page after tapping controls (mobile focus). Fix: RowMenu.vue positioning; .mp-skip focus-visible only. Evidence: built/11-dashboard-phone-overflow-menu-phone.png.
11. Low - Touch targets under 44px on dashboard/phone: Open Web Player link, Diagnostics summary, Manage all requests, Flagged page, breadcrumbs, player-band rate buttons (32px), queue search (40px). Fix: shared button/link utility, PartyPlayer.vue rate buttons. Evidence: audit.
12. Info - No-device, outside-party, relink-needed states and Reverb realtime not reproducible in this stack (fake provider, no Reverb); WebSocket 502 console error and "Reconnecting..." pill are environmental.

---

# Review: 13-search, 14-tv-player

Built dir: `/opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/` (B). Side-by-side pairs: `/tmp/task1349/pairs/<frame>.png` (mockup left, built right).
Data is the seeded fake provider, so copy/data differ (Fake Track vs Get Lucky). Those differences are ignored below unless they change layout.
New captures this run: 13 loading/error/no-close-matches (Inertia deferred response held or patched via Playwright route; weak flag injected because the fake provider cannot return weak results), 14 band-* (recaptured from seeded parties PLAY/PAUS/IDLE/EMPT; the earlier band shots all showed whatever state PLAY was in), tv-reconnecting (Reverb is down on this stack, so the real pill appears), tv-long-title (title text patched in the DOM), tv-qr-off (show_qrcode toggled off then restored), web-player-not-connected-phone.

## 13-search

| frame | verdict | reason | built |
|---|---|---|---|
| empty-desktop | deviates | Layout and content match (Playing now card, Recent searches chips, Most requested tonight tiles). h1 "Search" is 40px; mockup is 24px | B/13-search-empty-desktop.png |
| empty-desktop-dark | deviates | Same h1 size. Dark mode is emulated via data-bs-theme | B/13-search-empty-desktop-dark.png |
| empty-phone | deviates | h1 32px vs 24px. The phone breadcrumb collapses to "Home / ... / Search" with a stray gap before the "/" (long party name) | B/13-search-empty-phone.png |
| results-desktop | deviates | Top result, Tracks, Albums and Artists match. h1 is oversized | B/13-search-results-desktop.png |
| results-desktop-dark | deviates | Same | B/13-search-results-desktop-dark.png |
| results-phone | deviates | h1 oversized, breadcrumb collapse. Rows and sections match | B/13-search-results-phone.png |
| add-button-states-desktop | deviates (partial) | Mockup shows five Add states in one sheet. Built shows only plain Add on this query. "In queue with votes", "Requested by you · just now" and "Played in the last hour" show up in see-all-tracks and album/artist pages on other data. "Awaiting approval / Withdraw" is not seeded and was not seen | B/13-search-add-button-states-desktop.png |
| loading-desktop | matches | Placeholders keep real block sizes for Top result, Tracks, Albums and Artists headings | B/13-search-loading-desktop.png |
| error-desktop | matches | "Search didn't load", copy, Retry, query kept in the box. Error response patched in | B/13-search-error-desktop.png |
| no-results-desktop | matches | "No results for ..." card. h1 size aside | B/13-search-no-results-desktop.png |
| no-results-phone | matches | Same as desktop | B/13-search-no-results-phone.png |
| no-close-matches-desktop | matches | Notice above plain weak results. Copy says "nearest results Fake returned" (provider name interpolated; mockup says Spotify) | B/13-search-no-close-matches-desktop.png |
| see-all-tracks-desktop | matches | Narrow centred list, back link, count, pagination | B/13-search-see-all-tracks-desktop.png |
| see-all-tracks-phone | matches | Load more on phone | B/13-search-see-all-tracks-phone.png |
| album-desktop | matches | Built capture is viewport-only; top part matches | B/13-search-album-desktop.png |
| album-phone | matches (minor) | Breadcrumb collapses (long party name) | B/13-search-album-phone.png |
| artist-desktop | matches | Top tracks and Albums cards. Viewport-only capture | B/13-search-artist-desktop.png |

## 14-tv-player

| frame | verdict | reason | built |
|---|---|---|---|
| tv-playing | deviates (minor) | Adds a text join URL under the code (`localhost:8349/parties/PLAY`). The mockup note says "no text URL added, open question", but #422 requires the URL. Otherwise identical | B/14-tv-player-tv-playing.png |
| tv-paused | deviates (minor) | Same URL line. "Paused" pill matches | B/14-tv-player-tv-paused.png |
| tv-reconnecting | deviates (minor) | Built pill reads "Reconnecting…". The mockup shows the stale variant "Reconnecting… showing the last known track", which exists in code but needs a stale state. Captured with the real Reverb failure | B/14-tv-player-tv-reconnecting.png |
| tv-nothing-playing | deviates (minor) | Same URL line and QR. Mockup content sits slightly lower | B/14-tv-player-tv-nothing-playing.png |
| tv-qr-off | deviates (minor) | "Join with code" and no QR match. Adds the URL line | B/14-tv-player-tv-qr-off.png |
| tv-long-title | matches | Two-line clamp with ellipsis (title patched in the DOM) | B/14-tv-player-tv-long-title.png |
| band-playing-desktop | matches | PLAY | B/14-tv-player-band-playing-desktop.png |
| band-playing-phone | matches | | B/14-tv-player-band-playing-phone.png |
| band-paused-desktop | matches | PAUS, Paused pill top right | B/14-tv-player-band-paused-desktop.png |
| band-paused-phone | matches | | B/14-tv-player-band-paused-phone.png |
| band-nothing-desktop | matches | IDLE | B/14-tv-player-band-nothing-desktop.png |
| band-nothing-phone | matches | | B/14-tv-player-band-nothing-phone.png |
| band-no-next-desktop | matches | EMPT, "Nothing queued, search to add a track" | B/14-tv-player-band-no-next-desktop.png |
| band-no-next-phone | matches | | B/14-tv-player-band-no-next-phone.png |
| band-reconnecting-desktop | matches (not compared in detail) | Real pill after the Reverb failure | B/14-tv-player-band-reconnecting-desktop.png |
| band-reconnecting-phone | matches (not compared in detail) | | B/14-tv-player-band-reconnecting-phone.png |
| web-player-login-again-desktop | matches | Hero, Spotify link row, red status. Only reproducible Web Player state without the SDK | B/14-tv-player-web-player-login-again-desktop.png |
| web-player-not-connected-phone | deviates | On phone the "Spotify link" row breaks: the action button wraps beneath, the dot is misaligned and the text is pushed right. Button is 125x28 (under 44px) | B/14-tv-player-web-player-not-connected-phone.png |
| web-player-playing-desktop / -dark / -phone / -tv | not reproducible | Needs the Spotify Web Playback SDK and a Premium account. Not available in the local fake-provider stack | none |
| web-player-paused-desktop | not reproducible | Same | none |
| web-player-premium-needed-desktop | not reproducible | Raised by the SDK `account_error`. Copy exists in WebPlayer.vue | none |
| web-player-taken-over-desktop | not reproducible | Needs a second claiming player. "Use this tab instead" exists in code | none |

## Ticket criteria

- #428 (Search)
  - Empty-page suggestions from party data: met (recent searches and most-requested artists render from real data).
  - Weak notice: met. Reproduced only by patching the response, because the fake provider cannot return weak matches.
  - Add button states: partly seen (see above).
  - See all tracks narrow and centred: met.
  - Matches mockup: not met because of the h1 size.
  - No overflow at 390: met. Touch targets: see Audit.
- #422 (TV page)
  - Join URL on TV: met (URL line shown).
  - QR on by default: met (Party default `show_qrcode` is true).
  - 404 and party-not-found: not in my group.
- #425 (type scale and heading levels): the Search h1 does not follow the scale. On search pages the first heading in the DOM is the band's h2, before the h1.

## Audit (390x844, guest unless noted, light and dark identical)

- Search (empty, overview, type=track)
  - No overflow, no broken images, no console errors, no contrast failures.
  - Headings: "first heading is h2, not h1". The band title (h2) precedes the h1 (low severity; the mockup has the same structure).
  - Touch targets under 44px:
    - Band rate up/down buttons are 32x32.
    - Navbar toggler is 32x32.
    - User link is 32x32.
    - Inline artist and album links are 19px tall.
    - Footer links, breadcrumb "Home" and "Skip to content" (36px).
- TV (anon)
  - No overflow, heading OK, contrast OK.
  - Console: Reverb websocket 502 (stack limitation, not an app bug).
- Player (owner)
  - No overflow, heading OK.
  - One 404 console error (likely the Spotify SDK or an asset).
  - Touch target: "Log in with Spotify" is 125x28.
- Vocabulary: no "Fallback Playlist", "Flagged Request" or "Moderator" strings on these pages. "Paused" and "Not connected" are consistent between the band, TV and Web Player. Ages are in words ("12 minutes ago"). The TV shows a 4-letter code.

## Deviations

1. **Medium.** The Search h1 is `<h1>Search</h1>` with no page-title class, so it renders 40px on desktop and 32px on phone. The mockup uses 24px, and the Queue h1 is 24px.
   - Fix: `resources/js/pages/Parties/Search.vue:161`, add the shared page-title class or `--mp-fs-h1`. Do the same for the album and artist titles if they differ.
   - Evidence: measured with `/tmp/task1349/p13probe.mjs` and `/tmp/task1349/mockh1.mjs`; pairs for every 13 frame.
2. **Medium.** On phone, the Web Player status card breaks the Spotify link row. The action button wraps under, the dot is misaligned, and the button is 28px tall.
   - Fix: the `mp-checks` row style for the action slot in `resources/sass/_party.scss` and `WebPlayer.vue`.
   - Evidence: `/tmp/task1349/pairs/14-tv-player-web-player-not-connected-phone.png`.
3. **Low.** The TV shows a text join URL, which the mockup does not. #422 asks for it and the mockup flags it as an open question. Update the mockup or confirm.
4. **Low.** The phone breadcrumb collapses to "Home / ... /" with a stray gap before the separator when the party name is long.
   - Fix: the shared breadcrumbs component (`mp-crumb-toggle`).
   - Evidence: `13-search-results-phone.png`.
5. **Low.** The band rate buttons are 32x32 on phone. Result-row vote controls were not seen under 44px.
6. **Low.** The no-close-matches copy interpolates the provider name ("results Fake returned"). Confirm that is intended.
7. **Info.** The band h2 precedes the h1 in the DOM on every party page.

---

# Review: 20-requests, 21-moderation, 22-ai-reviews, 23-settings, 24-members, 25-create-party

Totals (67 frames): matches 30, deviates 30, missing 0, not reproducible 7.
Built shots: docs/mockups/design-critique/review/built/<frame>.png (full page; side-by-side pairs in /tmp/task1349/pairs/<frame>.png).
Scripts: /tmp/task1349/g20a.mjs, g20b.mjs, g20c.mjs, g20d.mjs, g20f.mjs. Audit data: /tmp/task1349/g20audit.jsonl.
Caveats: seed has 3 requests on PLAY (mockup 42), so data-dependent differences are not counted as deviations. Dark frames were captured by forcing body[data-bs-theme=dark]; dark pairs were not all inspected side by side, only the light twin plus axe contrast. Done screens (25) and notheld (21) could not be reached. Mods AI keyed state was captured on EMPT with a throwaway key (since removed), test parties deleted.
All built paths below are prefixed docs/mockups/design-critique/review/built/ and suffixed .png.

## 20-requests
| frame | verdict | reason | built |
|---|---|---|---|
| requests-default-desktop | matches | Layout, chips, hint line, row menu match. Minor: Votes/Score/Sent headers render link-blue (mockup grey), default sort caret points up (mockup newest-first, down) | 20-requests-requests-default-desktop |
| requests-default-desktop-dark | matches | same | 20-requests-requests-default-desktop-dark |
| requests-filtered-desktop | matches | Typed tokens press chips and add Artist chip, count "0 of 3" (seed has no ABBA rows) | 20-requests-requests-filtered-desktop |
| requests-nomatch-desktop | matches | Empty state repeats query, Clear filters filled button | 20-requests-requests-nomatch-desktop |
| request-detail-desktop | matches | Cards, AI review outcome link, History, Schedule match. "View on Spotify" absent (fake provider), Votes shows empty illustration (no votes seeded) | 20-requests-request-detail-desktop |
| request-detail-phone | deviates | Breadcrumb collapses party to "..." and long track title runs past viewport edge; card order History before Schedule/Votes (mockup AI review, Schedule, Votes, History) | 20-requests-request-detail-phone |
| requests-guest-desktop | deviates | Recaptured (#450) as review-member on RFLG with APP_DEBUG=false: branded 403 "You can't open Requests" with Back to the Queue. Still a 403, not a read-only list (mockup marks it undecided) | built/20-requests-requests-guest-desktop.png |
| requests-list-phone | deviates | Search placeholder overlaps the "N Requests" count; breadcrumb collapses to "..." (mockup shows full) | 20-requests-requests-list-phone |
| requests-menu-phone | deviates | Menu itself matches; same search/count overlap and collapsed breadcrumb | 20-requests-requests-menu-phone |

## 21-moderation
Common cause for most deviations below: on desktop the page title and description are centred (text-align) over a left-aligned tab bar, mockup is left aligned.
| frame | verdict | reason | built |
|---|---|---|---|
| flagged-list-desktop | deviates | Centred title/description. Rows, Approve/Reject, reason + "View AI review" link match | 21-moderation-flagged-list-desktop |
| flagged-list-desktop-dark | deviates | Same (light twin inspected) | 21-moderation-flagged-list-desktop-dark |
| flagged-bulk-desktop | deviates | Selection bar matches; centred title | 21-moderation-flagged-bulk-desktop |
| flagged-empty-desktop | deviates | Generic "no music" icon instead of green check; copy is the approval-off wording ("Turn on approval...") rather than "When a rule or AI review flags a Request..."; centred title | 21-moderation-flagged-empty-desktop |
| flagged-notheld-desktop | matches | Recaptured (#450) on RFLG: "Flagged Requests aren't held" notice with Turn on approval, empty state | built/21-moderation-flagged-notheld-desktop.png |
| rules-list-desktop | deviates | Centred title; Type shown Title Case ("Artist Name") vs sentence case | 21-moderation-rules-list-desktop |
| rules-list-desktop-dark | deviates | same | 21-moderation-rules-list-desktop-dark |
| rule-edit-desktop | deviates | Centred title; radio cards are half width and ordered Reject then Flag (mockup full width, Flag first); card footer crowds Notes row | 21-moderation-rule-edit-desktop |
| rule-delete-desktop | deviates | Built is a standalone page with a card, mockup is a modal over the Rules list | 21-moderation-rule-delete-desktop |
| moderator-flagged-desktop | deviates | Sidebar (no Settings) correct; centred title | 21-moderation-moderator-flagged-desktop |
| moderator-rules-desktop | deviates | Read-only notice correct and names owner; centred title | 21-moderation-moderator-rules-desktop |
| flagged-list-phone | deviates | Search placeholder overlaps count ("3 held" in mockup); breadcrumb collapsed | 21-moderation-flagged-list-phone |
| flagged-bulk-phone-dark | deviates | same overlap; bulk bar is above list, mockup pins it below | 21-moderation-flagged-bulk-phone-dark |
| rules-list-phone | deviates | search overlaps "N Rules" count | 21-moderation-rules-list-phone |

## 22-ai-reviews
| frame | verdict | reason | built |
|---|---|---|---|
| ai-list-desktop | deviates | Recaptured (#450) on RAIS. Centred title; breadcrumb "AI Reviews" vs "AI reviews"; empty criterion shows "-" not "None" | 22-ai-reviews-ai-list-desktop |
| ai-list-desktop-dark | deviates | Recaptured (#450) on RAIS. same | 22-ai-reviews-ai-list-desktop-dark |
| ai-filtered-desktop | deviates | Recaptured (#450) on RAIS. Outcome chip filter works (?outcome=flag); same centred title | 22-ai-reviews-ai-filtered-desktop |
| ai-empty-desktop | matches | Recaptured (#450) on REMP: "No AI reviews yet" empty state with outcome and criterion chips at 0 | built/22-ai-reviews-ai-empty-desktop.png |
| ai-detail-flagged-desktop | deviates | Recaptured (#450) on RAIS: criterion card shows all six bars. No Approve/Reject/Open Request because seeded reviews have no upcoming song. Tabs shown above the back link (mockup none); Criterion card has no per-criterion bars (seed answers empty, text "The model returned no per-question answers"); Request payload/Raw response lack disclosure markers; extra Endpoint row | 22-ai-reviews-ai-detail-flagged-desktop |
| ai-detail-flagged-desktop-dark | deviates | Recaptured (#450) on RAIS: criterion card shows all six bars. No Approve/Reject/Open Request because seeded reviews have no upcoming song. same | 22-ai-reviews-ai-detail-flagged-desktop-dark |
| ai-detail-low-desktop | deviates | Recaptured (#450) on RAIS: criterion card shows all six bars. No Approve/Reject/Open Request because seeded reviews have no upcoming song. Short title ("Satellite") is right aligned in header; no Open Request button when request is gone (data) | 22-ai-reviews-ai-detail-low-desktop |
| ai-detail-rejected-desktop | deviates | Recaptured (#450) on RAIS: criterion card shows all six bars. No Approve/Reject/Open Request because seeded reviews have no upcoming song. Short title ("Daybreak") right aligned; Open Request outline button missing | 22-ai-reviews-ai-detail-rejected-desktop |
| ai-list-phone | deviates | Recaptured (#450) on RAIS. search placeholder overlaps count | 22-ai-reviews-ai-list-phone |
| ai-detail-phone | deviates | Recaptured (#450) on RAIS: criterion card shows all six bars. No Approve/Reject/Open Request because seeded reviews have no upcoming song. Reject button overlaps the byline line; tabs above; no criterion bars | 22-ai-reviews-ai-detail-phone |

## 23-settings
| frame | verdict | reason | built |
|---|---|---|---|
| general-desktop | matches | Column, rows, country/timezone pickers, label "Party is running" | 23-settings-general-desktop |
| general-desktop-dark | matches | contrast axe shows no general-tab issues | 23-settings-general-desktop-dark |
| requests-desktop | matches | Raffle hint wraps narrower; Downvotes per person blank (seed null) | 23-settings-requests-desktop |
| playback-desktop | matches | Fallback Playlist picker with cover/count; open-picker also captured (23-settings-playback-picker-open-desktop) but not compared, fake provider lists one playlist | 23-settings-playback-desktop |
| screens-desktop | matches | Not inspected beyond audit (clean on contrast) | 23-settings-screens-desktop |
| mods-overview-desktop | matches | Collapsed AI card with stats row and switch; Whamageddon off row | 23-settings-mods-overview-desktop |
| mods-ai-desktop | matches | Engine & key, Decision slider + presets + explanation, Guidelines with chips and tips, party context, Advanced. Last review / Reviews today omitted although EMPT has AI reviews | 23-settings-mods-ai-desktop |
| mods-ai-desktop-dark | matches | | 23-settings-mods-ai-desktop-dark |
| mods-ai-error-desktop | not reproducible | Mockup shows confidence threshold 150 with inline "Enter a whole number from 0 to 100"; the form shows no inline error and Save leaves the value at 50. Not a seed gap, needs an app change | (none) |
| mods-ai-nokey-desktop | matches | Setup state with "Needs an API key" pill and numbered steps; rest dimmed | 23-settings-mods-ai-nokey-desktop |
| mods-trust-desktop | deviates | Expanded Trust is a plain field list (select, number inputs); mockup has stat row, Trusted User card with Change, slider, Advanced | 23-settings-mods-trust-desktop |
| mods-whamageddon-desktop | deviates | Whamageddon shares the plain pattern (likely, Trust inspected; no stat row for On state in my capture) | 23-settings-mods-whamageddon-desktop |
| unsaved-desktop | matches | Sticky "1 change: General - Discard - Save changes" bar | 23-settings-unsaved-desktop |
| general-phone | matches | Hints inside viewport; breadcrumb collapsed | 23-settings-general-phone |
| mods-phone | matches | cards stack, Settings button wraps; no overflow | 23-settings-mods-phone |

## 24-members
| frame | verdict | reason | built |
|---|---|---|---|
| list-desktop | matches | Search, role chips, role pills incl. Moderator | 24-members-list-desktop |
| list-desktop-dark | matches | | 24-members-list-desktop-dark |
| filtered-desktop | matches | q=role:user s query works | 24-members-filtered-desktop |
| detail-desktop | matches | Requests/Votes/Flags tables, Change role + Ban buttons (captured on Dmitri) | 24-members-detail-desktop |
| edit-role-desktop | matches | One-line role descriptions incl. Moderator "New" | 24-members-edit-role-desktop |
| ban-desktop | matches | Confirm modal over detail page | 24-members-ban-desktop |
| moderator-desktop | matches | Read-only notice, no actions | 24-members-moderator-desktop |
| list-phone | deviates | search placeholder overlaps count "11 members" | 24-members-list-phone |
| detail-phone | matches | | 24-members-detail-phone |

## 25-create-party
Reached with /parties/create?provider_id=fake (owner has no Spotify link, plain /parties/create redirects to /spotify).
| frame | verdict | reason | built |
|---|---|---|---|
| step1-desktop | matches | Validation error on step 1 ("Enter a name for your party.") | 25-create-party-step1-desktop |
| step1-ok-desktop | matches | | 25-create-party-step1-ok-desktop |
| step2-desktop | matches | Fallback Playlist picker (closed, mockup open), Play on | 25-create-party-step2-desktop |
| step3-desktop | deviates | Country and Timezone are native selects (Settings uses pickers); Requests per person empty, mockup default 3; section labels indented | 25-create-party-step3-desktop |
| done-desktop | matches | Recaptured (#450) as review-admin with the Fake provider: "is ready" card, code, join link, QR, Open Dashboard / Open TV / Party settings | built/25-create-party-done-desktop.png |
| done-desktop-dark | matches | Same, dark | built/25-create-party-done-desktop-dark.png |
| step1-phone | matches | | 25-create-party-step1-phone |
| step2-phone | matches | | 25-create-party-step2-phone |
| step3-phone | deviates | Subtitle text runs outside card; native selects | 25-create-party-step3-phone |
| done-phone | deviates | Recaptured (#450): card renders but the dark hero card clips on its right edge, QR stacks below the text | built/25-create-party-done-phone.png |

## Ticket criteria
- #417 vocabulary: Fallback Playlist, "Hold Flagged Requests for approval", "Party is running" used on Create/Settings; nav, title and first tab read Moderation / Flagged Requests (verified). Create uses "Create with defaults"; Dashboard/Spotify page not in this group. GLOSSARY.md entry not checked in this pass. Met for this group. Built still says "Fallback Track" in the mini-player (outside group).
- #424: name only required (Create with defaults works); picker not ID; labels match Settings; validation on step 1 shown. Partial: country/timezone use different controls; Requests per person has no default; created-party card unreachable for non-default provider; selecting Spotify without a linked account then Create with defaults throws RuntimeException "No linked Spotify account" (500). Feature tests not checked.
- #426: search bars, chips, no sidebars on Requests/Members/Moderation/AI reviews. Met. Phone search placeholder collides with count (fails the layout intent). Sent to Player column shows time or "Not yet" consistently.
- #429: pickers, sticky bar present; no horizontal overflow at 390 (all audited pages); touch targets fail (below).
- #430: AI card meets design; Trust/Whamageddon not carded in expanded state. Last review/Reviews today omitted (allowed).
- #431: moderator sees Dashboard, Queue, Search, Requests, Moderation, Members, no Settings; Rules read-only notice; Members read-only. Moderator opening /edit or /mods gets 403 (correct). Plain member gets 403 on Requests/Members/Flagged.
- #432: bulk approve/reject UI present (not clicked, no data change); AI review detail shows real fields but no per-criterion distribution when response lacks answers.
- #433: detail, role picker with Moderator line, ban confirm, moderator read-only all present.

## Audit (390px, owner light+dark, moderator light; /tmp/task1349/g20audit.jsonl)
- Horizontal overflow: none on any page.
- Headings: every page "first heading is h2, not h1" (mini-player track title is an h2 before the page h1); Settings Mods adds h2 to h4 ("Engine & key"). Contradicts #425.
- Broken images: none.
- Console errors: only Reverb WebSocket 502 (environment, no Reverb); 403s on moderator /edit and /mods (expected).
- Touch targets under 44px (site-wide): Skip to content 134x36, navbar toggle 32x32, user link 32x32, rate buttons 32x32, footer links. Page specific: row action buttons 40x40, tab links 39x41/46 ("Rules","Mods"), switches 32x20, checkboxes 20x20, radios 13x13 on Change role, copy buttons 28x28, create form inputs/buttons 40px tall, member names 23px tall.
- Contrast (axe): yellow-lt "Flagged" badges 1.97 (#f59f00), approve button white on green 2.62, azure/purple/orange role/outcome badges 2.7-4.3 light, red-lt 4.03/2.99, mod-card warning pill 3.3, ai-engine hint, input-group-text dark 3.78, link-secondary 4.42, btn-outline-secondary 4.42.

## Deviations
| severity | issue | suggested fix | evidence |
|---|---|---|---|
| high | Phone search field: long placeholder overlaps the result count on Requests, Moderation, Rules, AI reviews, Members | shorten placeholder on phone / hide count, search bar component shared | pairs/20-requests-requests-list-phone, 21-moderation-flagged-list-phone, 24-members-list-phone |
| high | Contrast fails (Flagged badge 1.97, green Approve 2.62) in light and dark | darken badge text / button background in shared style layer | audit |
| medium | Desktop page title+description centred on Moderation tabs, rule edit, AI review pages | remove centring on page head (Moderation/Index layout) | pairs/21-moderation-* |
| medium | AI review detail: short titles right aligned; Open Request missing when rejected; Reject overlaps byline on phone | fix header flex in resources/js/pages/Parties/AiReviews/Show.vue | pairs/22-ai-reviews-ai-detail-low-desktop, -phone |
| medium | Create Party store() redirects to parties.create with no provider_id, done card lost for non-Spotify provider; Spotify select without link gives 500 | keep provider_id in redirect / validate link in PartyController::store | built attempt: /spotify after create |
| medium | Heading outline: mini-player h2 precedes h1 on every page; h4 under h2 in Mods | demote mini-player title, use h3 | audit |
| medium | Touch targets under 44px (tabs, row buttons, switches, checkboxes, role radios, create inputs) | raise min-height on underline-tab, btn-icon, form-check | audit |
| medium | Trust/Whamageddon expanded settings are plain fields, not designed cards | extend ModCard (#430) | pairs/23-settings-mods-trust-desktop |
| low | Phone breadcrumb collapses party name to "..." and long titles overflow | breadcrumbs component | pairs/20-requests-request-detail-phone |
| low | Rule delete is a page not modal; edit radio order/width | PartyModeration delete/edit views | pairs/21-moderation-rule-delete-desktop |
| low | Flagged empty state icon/copy differ; notheld notice unverified | FlaggedRequests empty state | pairs/21-moderation-flagged-empty-desktop |
| low | Create step 3 native selects, no default Requests per person, subtitle overflow on phone | reuse Settings pickers; Parties/Create.vue | pairs/25-create-party-step3-* |
| low | Breadcrumb "AI Reviews" vs "AI reviews"; criterion "-" vs "None"; Type Title Case | copy | pairs/22-* |
| low | Request sort headers link-blue, default caret direction | table header style | pairs/20-requests-requests-default-desktop |
| low | Plain 403 page (unbranded) for member opening Requests | #422 error pages | pairs/20-requests-requests-guest-desktop |
| info | PLAY header showed "Nothing playing"/Neon Harbour fallback during capture; shared stack state changes between shots | n/a | |

---

# Admin group (30 dashboard, 31 users, 32 settings, 33 themes) - review of task-1349 build

Mockup frames: docs/mockups/design-critique/screenshots/<name>.png. Built: docs/mockups/design-critique/review/built/<name>.png
(full-page captures; side-by-sides in /tmp/task1349/pairs/<name>.png, mockup left, built right). Extra captures: /tmp/task1349/g30/.
Totals: 27 frames = 2 matches, 21 deviates, 0 missing, 4 not reproducible.
Role admin = Avery Admin. Seed gives only the built-in "Default" theme, 3 parties (all "Player stale" after 30s), no Horizon/Reverb, no branding values, no Mods installed, all social providers Off.

## Systemic root causes (affect many frames)
1. PageHead title block shifts right / centres on Settings, Users show/edit/delete, Provider edit (all pages without a trailing button group, and Users/Show where buttons stack below). Cause: Tabler `.page-header { flex-flow: column wrap }` is not overridden by `.mp-page-head { display:flex; justify-content:space-between; align-items:flex-end }`, so with column direction `align-items:flex-end` pushes the h1 block to the right edge of the container. Measured: head box x=368 w=960, title block x=967 w=361. Fix: add `flex-direction: row` to `.mp-page-head` in resources/sass/_party.scss (and the duplicate in _admin.scss if any) / resources/js/components/PageHead.vue. Severity: high (visible on ~12 frames).
2. Settings form labels render uppercase small-caps (Tabler `.datagrid-title`/label style), mockup uses sentence-case bold label + muted help. Fix: resources/js/pages/Admin/Settings/Index.vue label markup (use `.mp-field` style as User Edit does). Severity: medium.
3. Phone breadcrumb collapses to `Home / ... / Users` with a 32x44 "Show full path" button; mockup shows `Home / Admin / Users` inline. Severity: low (needs 44px width too).
4. Sidebar: mockup shows the current party item ("Coyote LAN 26") - built lacks it for admin (not a member), acceptable.

## 30-admin-dashboard
| frame | verdict | reason | built |
|---|---|---|---|
| admin-dash-busy-desktop | deviates (minor) | Structure, columns, badges, Needs attention, System health, Activity all match. Differences are data only: owner shows two-line "Olive Owner" (mockup single nickname); avatar+name top right vs "Jess"; Player stale on all 3 parties; Horizon/Scheduler/Reverb red (services not running here); no Playing/Paused/Nothing playing variety | review/built/30-admin-dashboard-admin-dash-busy-desktop.png |
| admin-dash-busy-desktop-dark | deviates (minor) | Same as light; dark tokens correct, no contrast issue found | review/built/30-admin-dashboard-admin-dash-busy-desktop-dark.png |
| admin-dash-busy-phone | deviates (minor) | Cards stack as in mockup (party card, attention, health, activity). Breadcrumb collapsed; "Open Dashboard" buttons wide and fine; party name links 19px tall | review/built/30-admin-dashboard-admin-dash-busy-phone.png |
| admin-dash-busy-phone-dark | deviates (minor) | As above, dark OK | review/built/30-admin-dashboard-admin-dash-busy-phone-dark.png |
| admin-dash-clear-desktop | deviates | Recaptured (#450) after `review:seed --all-clear`: Nothing needs attention, all health rows green including Reverb (ReverbStatus fixed for an empty channel list). Only difference: "No parties are live" (mockup shows 2 live parties; seeded parties have no reporting Player) | built/30-admin-dashboard-admin-dash-clear-desktop.png |
| admin-dash-quiet-desktop | matches | Recaptured (#450) with `review:seed --all-clear --quiet-parties`: "No parties are live", nothing needs attention | built/30-admin-dashboard-admin-dash-quiet-desktop.png |

## 31-admin-users
| frame | verdict | reason | built |
|---|---|---|---|
| admin-users-list-desktop | deviates | Missing "N users on this site, newest login first" subtitle (replaced by nothing; heading sits flush on search bar); filter chips include extra "Laravel Passport" and "Spotify Search" (mockup: Discord, Steam, Twitch); sorted by ID not last login; linked-accounts icons smaller and uncoloured (no needs-re-link amber state); no row action menu frame (not opened); Parties column shows 4 for everyone (seed artefact) | review/built/31-admin-users-admin-users-list-desktop.png |
| admin-users-filtered-desktop-dark | deviates | Built captured free-text "sa" (2 of 13) not `role:admin` with an active Admin chip; subtitle "N of M users match" absent; no pagination footer ("Showing 1 to 2 of 2") | review/built/31-admin-users-admin-users-filtered-desktop-dark.png |
| admin-user-detail-desktop | deviates (high) | Title right-shifted (root cause 1), no avatar; Impersonate/Edit sit below the title instead of beside it; Linked accounts table has Provider/Account/External ID instead of Provider/Account/Linked/State with a state pill and token-expiry note; Unlink button disabled/faded; no Parties card (Retro Room/Owner/last active); Account card has extra Created, mockup has none | review/built/31-admin-users-admin-user-detail-desktop.png |
| admin-user-detail-phone | deviates | No avatar next to name; actions stacked full-width (Impersonate over Edit, order reversed vs mockup Edit then Impersonate); Account card shown before Linked accounts and Linked accounts table overflows its card (table right=505 > 390, scrolls inside card, audit flagged); no Parties card | review/built/31-admin-users-admin-user-detail-phone.png |
| admin-user-edit-desktop | deviates | Title right-shifted; Roles row has an extra "Create Party" checkbox (mockup: Admin only); Accepted Terms row lacks help text; card not centred at 660px | review/built/31-admin-users-admin-user-edit-desktop.png |
| admin-user-delete-desktop | deviates | Title right-shifted; warning icon card OK; missing impact paragraph ("Removes 2 linked accounts ... Mina owns Retro Room, so that party needs a new owner first"); typed-confirm placeholder "delete" present | review/built/31-admin-users-admin-user-delete-desktop.png |
| admin-users-list-phone | deviates | Search placeholder text collides with the "13 users" count; rows are chevron links not avatar+meta+"..." menu; no avatar, no linked-account icons, no "3 parties - last login ..." meta (shows "#1 - never logged in"); subtitle missing; no pagination ("Showing 1 to 5 of 48 / Next") | review/built/31-admin-users-admin-users-list-phone.png |

## 32-admin-settings
| frame | verdict | reason | built |
|---|---|---|---|
| admin-settings-general-desktop | deviates | Title right-shifted; labels uppercase (root cause 2); sections Site / Defaults / Music providers present and ordered right; timezone select present; no "2 unsaved changes - Discard - Save changes" sticky bar (dirty state not shown; single Save position unverified); Fake provider is ON here (seed) | review/built/32-admin-settings-admin-settings-general-desktop.png |
| admin-settings-general-phone | deviates | Same label casing; sticky unsaved bar absent; toggles 32x20 below 44px target | review/built/32-admin-settings-admin-settings-general-phone.png |
| admin-settings-branding-desktop-dark | deviates | Recaptured (#450) with branding set: sidebar, white, cover and 32px favicon previews render. Labels/helpers uppercase; favicon caption below not inline; tab style differs | built/32-admin-settings-admin-settings-branding-desktop-dark.png |
| admin-settings-providers-desktop | deviates | Recaptured (#450): Discord "Sign-in on", Steam "Needs a secret", Spotify "Linking only" badges match. Row order differs (Spotify 5th), Spotify helper copy differs, tab strip style differs | built/32-admin-settings-admin-settings-providers-desktop.png |
| admin-settings-provider-edit-desktop | deviates | Recaptured (#450) at /admin/settings/socialproviders/2/edit (Steam, empty secret): needs-secret error, toggles, return URLs, Cancel/Save match. No Client ID row, secret input red-outlined (mockup neutral), "Shown only once" copy missing | built/32-admin-settings-admin-settings-provider-edit-desktop.png |
| admin-settings-themes-desktop | deviates | Themes tab present with Add theme and table (thumb, status, mode, updated, actions) and footer note. Only built-in Default exists (no Active badge / Delete / Edit actions seen); Default row's action is "View" with icon vs mockup plain; breadcrumb stays "Settings" | review/built/32-admin-settings-admin-settings-themes-desktop.png |
| admin-mods-list-desktop | deviates | Recaptured (#450): populated list renders (Moderation (2) / Fun (1), cards, AI Request Review stats). Narrower offset content column, small heading, no per-card enable toggle | built/32-admin-settings-admin-mods-list-desktop.png |
| admin-mods-empty-desktop | not reproducible | Three mods ship with the app, so the empty state cannot show without uninstalling mods. Built file unchanged | built/32-admin-settings-admin-mods-empty-desktop.png |

## 33-admin-themes
| frame | verdict | reason | built |
|---|---|---|---|
| admin-themes-list-desktop | deviates | Recaptured (#450) at /admin/themes with StratLAN active: breadcrumb, subtitle, Add theme, 4 themes, Make active / Edit / Delete. No settings tab strip, full-width content, no card header | built/33-admin-themes-admin-themes-list-desktop.png |
| admin-theme-create-desktop | matches | Two-column editor, Name / Dark mode / Active, 2-col colour grid with "Default" button and "Using the Music Party default" (never black), full-width Custom CSS, preview with Light/Dark switch and Primary/Outline/Delete buttons. Minor: Custom CSS editor is a light box, mockup uses a dark code block; label "Navigation Background"/"Muted Text" capitalised vs "Navigation background"; Dark mode help text wording differs slightly | review/built/33-admin-themes-admin-theme-create-desktop.png |
| admin-theme-invalid-desktop | matches | Built file was identical to create (invalid state not driven). Recaptured by typing #d6393 into Primary: red outline, X icon, inline message "That is not a colour. Use #RGB or #RRGGBB, for example #d63939." and "1 colour needs fixing" badge, exactly as mockup. Preview note about keeping the default not checked | /tmp/task1349/g30/theme-invalid.png |
| admin-theme-edit-stratlan-desktop-dark | matches | Recaptured (#450) with StratLAN active: Details, 7 colour tokens with Reset, Custom CSS, Light/Dark preview, Cancel/Save. Minor: dark-mode helper copy and title-case token labels | built/33-admin-themes-admin-theme-edit-stratlan-desktop-dark.png |
| admin-theme-delete-desktop-dark | matches | Recaptured (#450) on active StratLAN (not confirmed): "It is the active theme. The site goes back to the Default theme straight away" warning, thumb, Cancel / Delete | built/33-admin-themes-admin-theme-delete-desktop-dark.png |
| admin-theme-edit-phone | deviates | Recaptured (#450) on StratLAN with enabled fields; not re-compared line by line | built/33-admin-themes-admin-theme-edit-phone.png |

## Ticket criteria
#434 Useful Admin dashboard
- Every figure real data / noted: PASS (AdminDashboard service + AdminDashboardTest exist; figures render; Horizon/Reverb red correctly because they are not running).
- Feature tests cover dashboard data: PASS (tests/Feature/Admin/AdminDashboardTest.php).
- Matches mockups every frame: PARTIAL - busy light/dark/phone ok; clear and no-parties frames not verified.
- Light+dark, no overflow 390, touch >=44: overflow PASS; touch FAIL (party links 19px, breadcrumb toggle 32px, navbar toggler 32px, skip link 36px; same shell items fail on every page); contrast PASS.
#435 Admin settings, branding, social providers
- Saving persists (existing tests pass): not run (read-only), no regression observed.
- Matches mockups 31 + 32: FAIL (see deviations: previews, provider status/edit, user detail Parties/State, labels).
- Light/dark, overflow, touch: overflow PASS on all settings pages; touch FAIL (settings toggles 32x20, edit-user checkboxes 20x20 and toggles 32x20).
#436 Admin Themes editor
- Unset colours save as unset, not black (test): PASS in UI (shows "Default", Reset only when set); AdminThemePagesTest exists.
- Invalid colour shows inline error: PASS (verified live).
- Matches mockups every frame: FAIL (list is a tab not a page; StratLAN/delete states not producible; code editor styling).
- Light/dark, overflow, touch: overflow PASS; dark/light contrast FAIL on preview badges (see Audit); touch FAIL (inputs 40px, toggles 32x20).
#426 Admin Users (31): search bar above table, no sidebar PASS; chips present PASS; phone cards "proper padding" PASS; mockup match FAIL (see 31 table).

## Audit (390x844, role admin, light/dark)
| page | overflow | headings | contrast | console | touch <44 |
|---|---|---|---|---|---|
| /admin | pass | h1 x1, no skips | pass | pass | fail: party links 19px + shell (skip link 36, toggler 32, breadcrumb toggle 32x44, user link 32, footer links) |
| /admin/users | pass | pass | pass | WS 502 (Reverb not running; env) | fail: 13 user name links 19px tall |
| /admin/users/6 | FAIL: linked-accounts table right edge 505px (card scroll, page itself not overflowing) | pass | pass | pass | fail: email link 19px |
| /admin/users/6/edit | pass | pass | pass | WS 502 dark only | fail: checkboxes 20x20, toggles 32x20 |
| /admin/users/6/delete | pass | pass | FAIL: 6 nodes (.mt-4, label, strong, "Cancel" link) ratio 2.22 light (#404040 on dark bg) and 3.7 dark - likely text colour tokens for the danger card mismatched with surface; re-check after fade-in | pass | fail: shell only |
| /admin/settings | pass | pass | pass | WS 502 dark only | fail: Spotify/Fake toggles 32x20 |
| /admin/settings/socialproviders/2/edit | not run on 2 (path 404 earlier); recapture shows layout fine | | | | |
| /admin/mods | pass | pass | pass | WS 502 dark only | fail: shell only |
| /admin/settings/themes/create | pass | pass | FAIL: preview badges ".pv-badge" white on #2fb344/#f76707 ratio 2.74 and 3.04 (both schemes) - preview of default Success/Warning tokens, mirrors real app contrast issue | pass | fail: name input 40px, toggles 32x20, colour inputs 28x44 (width), Reset/Default buttons |
Shell touch targets (skip link, navbar toggler, breadcrumb toggle, user link, footer credit/Source) fail on every page; one fix in the layout.

GLOSSARY vocabulary: Dashboard uses "Requests waiting", "Flagged", "Player stale / Playing / Paused / Nothing playing" with one pill style across Live parties and Needs attention - consistent. "Moderator", "Fallback Playlist" not used in this group. Ages are in words everywhere ("4 minutes ago", "never logged in"). Party codes shown as 4 letters (EMPT, PLAY, PAUS). One difference: Live-parties column header "Flagged" lacks the noun "Flagged Requests" (acceptable in a tight column). No "joined X ago" wording issue.

## Deviations (ranked)
1. HIGH - Title block pushed right on most admin pages. Evidence: /tmp/task1349/pairs/31-admin-users-admin-user-detail-desktop.png, 32-admin-settings-*. Fix: `flex-direction: row` on `.mp-page-head` (resources/sass/_party.scss, also check resources/sass/_admin.scss) or PageHead.vue.
2. HIGH - Admin Mods page does not match #430 / mockup 32 (empty, wrong breadcrumb `Home / Mods`, wrong empty copy). Fix: resources/js/pages/Admin/Mods.vue + ModsController; verify mod registry populated in env.
3. HIGH - Admin user detail: missing Parties card, Linked accounts State/Linked columns, token-expiry note, avatar, action placement. Fix: resources/js/pages/Admin/Users/Show.vue (+ UserController data).
4. MEDIUM - Users list: extra provider chips (Passport, Spotify Search), no subtitle with count, default sort ID not last login, no colour state on linked-account icons, phone rows are chevron links not avatar+meta+menu, search placeholder collides with count on phone, no pagination footer. Fix: Users/Index.vue.
5. MEDIUM - Delete user page lacks impact summary (linked accounts count, owned parties). Fix: Users/Delete.vue + controller.
6. MEDIUM - User edit shows extra "Create Party" role checkbox and lacks Accepted terms help. Fix: Users/Edit.vue.
7. MEDIUM - Settings: uppercase labels, no sticky "N unsaved changes / Discard / Save" bar, branding previews absent (or untestable with empty seed), provider status badges and Steam client id/secret form not as mockup. Fix: Settings/Index.vue, SocialProviders/Edit.vue.
8. MEDIUM - Themes list is only a Settings tab; mockup 33 has its own "Themes" page and breadcrumb Settings / Themes. Fix: ThemeController index route or adjust mockup note.
9. MEDIUM - /admin/settings/themes/1/delete shows a raw 403 Ignition page for the built-in theme. Fix: ThemeController::delete redirect with message instead of abort(403).
10. MEDIUM - Contrast: theme preview badges (white on #2fb344 / #f76707) 2.74/3.04; delete-user page text #404040 on dark surface 2.22 (light) / 3.7 (dark). Fix: preview badge text colour tokens; Users/Delete.vue card text colours.
11. MEDIUM - Touch targets under 44px: shell items on every page, user/party name links in tables (19px tall), form toggles 32x20, edit-user checkboxes 20x20. Fix: .mp-touch rule in _party.scss / table link padding; layout shell buttons.
12. LOW - Phone breadcrumb collapsed to "..." instead of Admin; Linked accounts table scrolls inside card on phone user detail; Custom CSS editor light-coloured vs dark code block in mockup; label casing "Navigation Background".
13. SEED GAPS (not app bugs) - to reproduce remaining frames the seed needs: themes StratLAN (active, dark, tokens + CSS) and Reference Theme; branding values; providers Discord on / Spotify linking only / Steam on with empty secret; a user with Spotify needs-re-link and owned party; healthy/unhealthy services for the all-clear and no-parties dashboard frames. Replace review/built files for 30 clear/quiet, 32 provider-edit, 32 mods-empty, 33 themes-list/edit-stratlan/delete once seeded.

---

# Report: 40-home-join, 41-errors, 42-profile, 43-type-shell + nav per role

Counts: deviates 27, matches 22, not reproducible 9 (total 58 frames; built dir /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/)

Note: `built` filename = mockup frame name. Env limits: local stack has APP_DEBUG=true, fake music provider, no Discord/Spotify/Reverb. Debug-off checks used a separate php -S server in the container (:9001); the shared stack was not modified.

## Frames

| frame | verdict | reason | built screenshot |
|---|---|---|---|
| 40-home-join-login-desktop | matches | Recaptured (#450): Login with Discord and Login with Steam, logo, cover panel (seed cover is a placeholder). Steam button is extra (seed) | built/40-home-join-login-desktop.png |
| 40-home-join-login-phone | matches | Recaptured (#450): same, cover hidden on phone | built/40-home-join-login-phone.png |
| 40-home-join-home-desktop | matches | Hero, party rows, role badge, code, state pill, Join card match. Data differs (seed). Playing line shows title only, mockup "Title, Artist" (low). | /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/40-home-join-home-desktop.png |
| 40-home-join-home-desktop-dark | matches | Dark tokens match. | /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/40-home-join-home-desktop-dark.png |
| 40-home-join-home-phone | matches | Stacks as mockup; Join button 44px. | /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/40-home-join-home-phone.png |
| 40-home-join-home-phone-dark | matches |  | /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/40-home-join-home-phone-dark.png |
| 40-home-join-home-empty-desktop | matches | Empty state, tilted tile, Create party, Join card match (captured as admin with no parties). | /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/40-home-join-home-empty-desktop.png |
| 40-home-join-home-code-error-desktop | matches | Digit error "Party codes are four letters, no digits." with invalid input, matches copy and placement. | /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/40-home-join-home-code-error-desktop.png |
| 40-home-join-home-code-error-phone | matches | Matches (viewport crop; error text below fold, same as mockup full-frame). | /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/40-home-join-home-code-error-phone.png |
| 40-home-join-home-anon-desktop | matches | Recaptured (#450): Join card, OR, "Hosting a party? Login to create one.", Discord + Steam buttons | built/40-home-join-home-anon-desktop.png |
| 40-home-join-home-anon-phone | matches | Recaptured (#450): same on phone | built/40-home-join-home-anon-phone.png |
| 40-home-join-home-anon-desktop-dark | matches | Recaptured (#450): same, dark | built/40-home-join-home-anon-desktop-dark.png |
| 40-home-join-parties-desktop | matches | /parties renders list with Home / Parties breadcrumb, same style as Home. No 500. | /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/40-home-join-parties-desktop.png |
| 40-home-join-parties-phone | matches |  | /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/40-home-join-parties-phone.png |
| 40-home-join-join-login-desktop | matches | Recaptured (#450) on /parties/RFLG: invite card, 3-step bar, "Login to add Requests and vote.", Discord + Steam, return note | built/40-home-join-join-login-desktop.png |
| 40-home-join-join-login-desktop-dark | matches | Same, dark | built/40-home-join-join-login-desktop-dark.png |
| 40-home-join-join-login-phone | matches | Recaptured (#450): invite card, compact step bar, Discord + Steam | built/40-home-join-join-login-phone.png |
| 40-home-join-join-nickname-desktop | deviates | Built /login/signup rendered with no party context (needs a real OAuth return with intended URL): shows "Create Account", no step bar, no "You're joining ... (CODE)". Code path with party exists in Users/Signup.vue (JoinSteps current=2, "Pick a nickname"), so the party variant is not captured here; the button reads "Continue" vs mockup "Continue to the party" in the no-party variant. Partially reproducible. | /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/40-home-join-join-nickname-desktop.png |
| 40-home-join-join-nickname-phone | deviates | Same as desktop. | /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/40-home-join-join-nickname-phone.png |
| 41-errors-e404-desktop | matches | Branded 404 on stage band, Go to Home + Enter party code, Home / Page not found breadcrumb. | /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/41-errors-e404-desktop.png |
| 41-errors-e404-phone | matches |  | /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/41-errors-e404-phone.png |
| 41-errors-e404-desktop-dark | matches |  | /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/41-errors-e404-desktop-dark.png |
| 41-errors-e404-anon-desktop | matches | Guest layout with photo panel, Enter party code + Login (Discord icon). | /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/41-errors-e404-anon-desktop.png |
| 41-errors-e500-desktop | not reproducible | Cannot be triggered without a throwing route; ErrorPageResponder returns the raw response when APP_DEBUG=true (this stack is local/debug). Code + tests cover it (ErrorPagesTest "never exposes exception message for a 500"). | - |
| 41-errors-e500-desktop-dark | not reproducible | As above. | - |
| 41-errors-e500-phone | not reproducible | As above. | - |
| 41-errors-e403-desktop | deviates | Matches layout. Title reads "You can't open Dashboard" vs mockup "You can't open the Dashboard" (article missing; low). | /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/41-errors-e403-desktop.png |
| 41-errors-e403-banned-desktop | matches | Names party and owner, Go to Home. Breadcrumb has party crumb (mockup omits it; trivial). | /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/41-errors-e403-banned-desktop.png |
| 41-errors-e403-phone | deviates | Breadcrumb shows Home / ... / Not allowed (collapsed, 3 crumbs); mockup shows Home / Not allowed. Heading wraps at "Dashboard" without "the". | /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/41-errors-e403-phone.png |
| 41-errors-party-not-found-desktop | deviates | BLANK PAGE (white 1440x900). With APP_DEBUG=false (php -S :9001) /parties/ZZZZ returns the Error component but with NO shared props (SubstituteBindings runs before HandleInertiaRequests in the web group), so useColorMode -> applyTheme(undefined) throws "Cannot read properties of undefined (reading 'tokens')" and Vue never renders. In debug mode (this stack) the raw Laravel "Not Found" page is shown. Either way the party-not-found recovery page is unusable. HIGH. | /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/41-errors-party-not-found-desktop.png |
| 41-errors-party-not-found-digit-desktop | deviates | BLANK PAGE (white 1440x900). With APP_DEBUG=false (php -S :9001) /parties/ZZZZ returns the Error component but with NO shared props (SubstituteBindings runs before HandleInertiaRequests in the web group), so useColorMode -> applyTheme(undefined) throws "Cannot read properties of undefined (reading 'tokens')" and Vue never renders. In debug mode (this stack) the raw Laravel "Not Found" page is shown. Either way the party-not-found recovery page is unusable. HIGH. | /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/41-errors-party-not-found-digit-desktop.png |
| 41-errors-party-not-found-short-desktop | deviates | BLANK PAGE (white 1440x900). With APP_DEBUG=false (php -S :9001) /parties/ZZZZ returns the Error component but with NO shared props (SubstituteBindings runs before HandleInertiaRequests in the web group), so useColorMode -> applyTheme(undefined) throws "Cannot read properties of undefined (reading 'tokens')" and Vue never renders. In debug mode (this stack) the raw Laravel "Not Found" page is shown. Either way the party-not-found recovery page is unusable. HIGH. | /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/41-errors-party-not-found-short-desktop.png |
| 41-errors-party-not-found-phone | deviates | BLANK PAGE (white 1440x900). With APP_DEBUG=false (php -S :9001) /parties/ZZZZ returns the Error component but with NO shared props (SubstituteBindings runs before HandleInertiaRequests in the web group), so useColorMode -> applyTheme(undefined) throws "Cannot read properties of undefined (reading 'tokens')" and Vue never renders. In debug mode (this stack) the raw Laravel "Not Found" page is shown. Either way the party-not-found recovery page is unusable. HIGH. | /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/41-errors-party-not-found-phone.png |
| 41-errors-party-not-found-digit-phone | deviates | BLANK PAGE (white 1440x900). With APP_DEBUG=false (php -S :9001) /parties/ZZZZ returns the Error component but with NO shared props (SubstituteBindings runs before HandleInertiaRequests in the web group), so useColorMode -> applyTheme(undefined) throws "Cannot read properties of undefined (reading 'tokens')" and Vue never renders. In debug mode (this stack) the raw Laravel "Not Found" page is shown. Either way the party-not-found recovery page is unusable. HIGH. | /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/41-errors-party-not-found-digit-phone.png |
| 41-errors-party-not-found-anon-desktop | deviates | Anon /parties/ZZZZ redirects to /login ("Login to join the party!") instead of showing the not-found page with the typed code. Anon /parties/ZZZZ/tv hits the same blank-page bug. HIGH (guests type codes off the TV). | /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/41-errors-party-not-found-anon-desktop.png |
| 41-errors-feature-off-owner-desktop | matches | Recaptured (#450) on RNOA with APP_DEBUG=false: "AI reviews are off for this party", Open Mods settings + Go to Moderation | built/41-errors-feature-off-owner-desktop.png |
| 41-errors-feature-off-moderator-desktop | matches | Recaptured (#450) as review-moderator: moderator copy, single Go to Moderation | built/41-errors-feature-off-moderator-desktop.png |
| 41-errors-feature-off-owner-phone | matches | Recaptured (#450): owner, phone | built/41-errors-feature-off-owner-phone.png |
| 42-profile-profile-desktop | deviates | Page NOT restyled: h1 "Linked Accounts" with legacy Tabler avatar card beside a right-hand profile card with "Edit" button; mockup has "Profile" page head, name/email card, "Linked accounts" cards with Linked status, Unlink, Manage Spotify links, Link another account. HIGH (whole page). | /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/42-profile-profile-desktop.png |
| 42-profile-profile-phone | deviates | Same legacy layout. | /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/42-profile-profile-phone.png |
| 42-profile-profile-desktop-dark | deviates | Same legacy layout. | /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/42-profile-profile-desktop-dark.png |
| 42-profile-spotify-desktop | deviates | Two cards Playback/Search exist with explanatory copy and linked state (#417 criterion met), but no icon tiles, no stacked "Link both to host a party" card (built places a disabled Create party button below with hint text), h1 style/summary differ, card titles h2 plain. | /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/42-profile-spotify-desktop.png |
| 42-profile-spotify-phone | deviates | As desktop. | /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/42-profile-spotify-phone.png |
| 42-profile-spotify-desktop-dark | deviates | As desktop; white-on-green buttons fail contrast 2.62. | /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/42-profile-spotify-desktop-dark.png |
| 42-profile-spotify-ready-desktop | matches | Recaptured (#450) as review-admin at /spotify: Playback + Search linked, "Ready to host" with Create party | built/42-profile-spotify-ready-desktop.png |
| 42-profile-profile-edit-desktop | deviates | Legacy: h1 "Edit Profile", hint "Your preferred nickname", button "Save"; mockup "Edit profile", "How you appear in parties.", hint "Shown next to your Requests.", button "Save changes", wider mp-narrow card. Breadcrumb OK. | /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/42-profile-profile-edit-desktop.png |
| 42-profile-profile-edit-desktop-dark | deviates | As light. | /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/42-profile-profile-edit-desktop-dark.png |
| 42-profile-unlink-desktop | matches | Recaptured (#450) as review-two-links: "Unlink Discord?" card, Keep linked / Unlink Discord | built/42-profile-unlink-desktop.png |
| 42-profile-unlink-phone | matches | Recaptured (#450): same, breadcrumb collapses to "..." | built/42-profile-unlink-phone.png |
| 43-type-shell-type-light-desktop | matches | Frame is a specimen. Verified computed styles on real pages: page head h1 40px/42px/800 (1.05) on Settings and Member detail, 32px on phone, p 16px/24.8px. Dashboard h1 is 24/600 inside the player band (does not follow the 40/800 page head; low-medium). | /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/43-type-shell-type-light-desktop.png |
| 43-type-shell-type-dark-desktop-dark | matches | Same metrics under dark. | /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/43-type-shell-type-dark-desktop-dark.png |
| 43-type-shell-type-phone | matches | Phone h1 32px/33.6px/800. | /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/43-type-shell-type-phone.png |
| 43-type-shell-type-phone-dark | matches |  | /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/43-type-shell-type-phone-dark.png |
| 43-type-shell-heading-outline-desktop | deviates | Screenshot is Dashboard full page. Outline differs: Dashboard has h1 Dashboard, then h2 "Player stale" (state pill used as heading), h2 Start Playback, h2 Upcoming Songs, h2 Needs attention, h2 Quick links; mockup is h1 > h2 Player > h3 Start playback/Health. Settings: h1 Settings, h2 Party only (mockup General/Requests/Playback/Screens/Mods); /mods page uses h4 under h2. Members/detail OK. Profile h1 "Linked Accounts". | /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/43-type-shell-heading-outline-desktop.png |
| 43-type-shell-breadcrumbs-phone | matches | Collapse button "..." with aria-label "Show full path: ...", middle crumbs hidden below sm, current page last. Built on Member detail. Opened-path sheet captured separately (breadcrumbs-open-phone). | /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/43-type-shell-breadcrumbs-phone.png |
| 43-type-shell-skip-link-desktop | matches | First Tab stop, position fixed, z-index 1090, visible over the sidebar with focus ring; href #main. | /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/43-type-shell-skip-link-desktop.png |
| 43-type-shell-sidebars-desktop | matches | Composite of real sidebars per role (guest/moderator/owner/admin). Link sets match mockup: guest Queue+Search only; moderator no Settings/Create Party, Moderation badge; owner adds Settings and Create Party; admin group Dashboard, Users, Settings, Mods, Pulse, Horizon. Built lists every party the user belongs to (mockup shows one). | /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/43-type-shell-sidebars-desktop.png |
| 43-type-shell-sidebars-desktop-dark | matches | Same, dark. | /opt/harmonic/worktrees/task-1349/docs/mockups/design-critique/review/built/43-type-shell-sidebars-desktop-dark.png |

## Ticket criteria

### #416 /parties error
- `/parties` no longer errors for guest, member, owner, admin: PASS (guest/owner 200 list; anon redirects to /login). Admin not separately loaded, same controller.
- Feature test for the behaviour / no other route to a missing method: not verified here (code-side, not run).
- Mockup frames parties-desktop/phone: PASS.

### #422 branded errors
- 404 uses app layout (logged in) / guest layout (anon) with a way home: PASS (e404-*).
- "Party not found" offers Enter party code field: FAIL in practice. Page renders blank in production (see D1); in debug shows bare "Not Found". Anon is redirected to login instead (D2).
- Error details never shown in production: PASS for the 404 path tested (debug-off server); in this stack APP_DEBUG=true so the responder is bypassed (by design, tested).
- TV page shows join URL and QR on by default: PASS (/parties/PLAY/tv shows "Scan or join with code PLAY localhost:8349/parties/PLAY" and a QR; migration defaults show_qrcode true).
- Feature tests cover 404 and party-not-found: tests exist but assert component props only, so they miss the blank-page bug (D1).

### #437 Home
- Code entry exactly four letters, uppercases, rejects digits with plain message: PASS (play -> PLAY; pl4y and PLA show "Party codes are four letters, no digits."; maxlength 4; unknown ZZZZ "We can't find a party with that code. Check the TV or ask the host."). Minor: " play " (leading space) fails as " PLA"; no trim on paste (low).
- Logged-out join returns guest after login: UI side OK (/parties/PLAY anon -> /login with invite card, "You'll come straight back to this party"); actual OAuth return not testable (no provider).
- Mockup frames all: see table (Discord button missing is env; nickname frame partial).
- Light and dark correct: PASS visually. No horizontal overflow at 390: PASS on all audited pages. Touch targets >= 44px on phone: FAIL (see Audit).

### #425 type/headings/breadcrumbs/skip link
- Type scale: page head h1 40/800 (32 phone) PASS on Settings/Members detail; Dashboard h1 24/600 differs.
- No page skips a heading level: no h1->h3 skips found on audited pages, but several pages START with an h2 (player band party/track title) before the h1: Queue/Request pages for member, Settings, Member detail (audit "first heading is h2, not h1"). Dashboard puts "Player stale" status pill text as h2 and Start Playback as h2 (mockup: h3).
- Shared breadcrumbs with separators: PASS (Home / x / y on all audited pages; phone collapse works).
- Skip link first Tab stop: PASS.
- Phone Settings hints clear of edge: not checked (outside this group).

### #417 (42-profile part)
- Spotify page explains each of the two links: PASS (Playback "Lets Music Party play on your Spotify for parties you host." / Search "Lets guests search Spotify's catalogue.").
- Fallback Playlist vocabulary: "Fallback Playlist is next" on Home PASS; "Fallback Track" remains on TV player and request detail (glossary allows Fallback Track as the track term).
- 42-profile restyle frames not built: see D3.

### #431 Moderator role (sidebar frames only)
- Sidebar hides what moderators cannot use: PASS (no Settings, no Create Party, no admin links; Moderation badge 3). Moderator Home shows Moderator role badge and "Open Dashboard". Moderator GLOSSARY entry present. Guest Dashboard 403 page names role and owner.

### GLOSSARY vocabulary
- Flagged Request, Moderator, Fallback Playlist: used consistently in group pages. No "Backup playlist"/"Pending" in group pages. Ages in words ("last update 15 minutes ago", "Joined 13 minutes ago"). Codes 4 letters upper-case. One state pill set (Playing/Paused/Player stale/Nothing playing) used on Home, Parties, invite.

## Audit (390x844, axe contrast; details in /tmp/task1349/au/*.json)
- Horizontal overflow: none on any of 26 page/role/scheme combinations.
- Broken images: none. Console errors: only Reverb WebSocket 502 (env, no Reverb) and expected 404/403 resource lines.
- Heading: / (all roles) one h1, no skips. /parties/PLAY anon invite page has 2 h1 (brand and party). Party pages (guest queue, Settings, Songs, Member detail) start with h2 of the player band before the page h1. Profile h1 "Linked Accounts" then h2 (legacy).
- Touch targets under 44px on phone (every logged-in page): navbar toggler 32x32, user avatar link 32x32, brand link 115x20 / 138x24 (guest layout), breadcrumb Home link 39x17, footer "Source" 47x17 and credit link 207x24, form inputs 40px high and buttons 40px (Profile Edit, Signup, Spotify Link buttons 40px, Cancel/Save 40px), Profile "Edit" 83x40, guest queue vote buttons (rate-button) 32x32, breadcrumb crumb toggle 32x44. Home specifically: toggler, avatar, brand, footer links. Violates #437 criterion.
- Contrast (light): Home role badge "Owner" (blue-lt) 4.36, "User" badge 4.19, idle pill "Nothing playing" 4.47 (needs 4.5); Spotify "Link Spotify for ..." white on green 2.62; Requests "yellow" badge 1.97 (outside group). Dark contrast: Home owner 7 items (similar badges); anon/signup/profile clean.
- Admin Users has 21 undersized targets on phone (outside group).

## Deviations
| id | severity | deviation | suggested fix | evidence |
|---|---|---|---|---|
| D1 | High | Party-not-found (and any ModelNotFound 404 raised in SubstituteBindings) renders a blank page in production: Error page has no shared props, `useColorMode` throws on `theme.tokens`. | Put `HandleInertiaRequests` before `SubstituteBindings` in the web group in `bootstrap/app.php` (lines ~66-67), and make `applyTheme` tolerate undefined (`resources/js/lib/theme.ts`, `composables/useColorMode.ts`); add a feature test that renders the real page (Inertia version/shared props) or a Vitest test mounting Error without theme. | built/41-errors-party-not-found-*.png (blank); curl X-Inertia on :9001 shows props only status,title,message,partyNotFound,typedCode,icon,actions,breadcrumbs |
| D2 | High | Anon `/parties/ZZZZ` redirects to /login instead of the not-found page with code entry; anon `/parties/ZZZZ/tv` shows blank (D1). | Run party lookup before auth for join links, or have Authenticate redirect carry the code; mockup party-not-found-anon shows branded page with prefilled code. | built/41-errors-party-not-found-anon-desktop.png, Playwright final URL /login |
| D3 | High | Profile, Edit profile, (unlink) not restyled to 42-profile mockup (legacy Tabler "Linked Accounts" layout, h1 "Linked Accounts", "Edit Profile"/"Save"). Spotify page lacks icon tiles and the "Link both to host a party" status card. | Rework `resources/js/pages/Users/Profile.vue`, `Edit.vue`, `Spotify.vue`, `LinkedAccountDelete.vue` to the mockup (page head, name/email card, Linked accounts cards with Linked state, Unlink, Manage Spotify links, Link another account; mp-narrow; Save changes). No ticket 416-437 clearly owns Profile besides #417 (Spotify copy) and #425 (heading); may need a ticket. | built/42-profile-profile-*.png vs screenshots |
| D4 | Medium | Touch targets below 44px on phone across the shell (toggler, avatar, brand, breadcrumb Home, footer links, form fields/buttons 40px, vote buttons 32px). #437/#431 criteria require >= 44px. | Raise min-height/width on `.navbar-toggler`, user menu link, `.breadcrumb a`, footer links, `.form-control`/`.btn` at <=576px in the shared style layer (`resources/css` / AppLayout). | /tmp/task1349/au/*.json touchTargetsUnder44 |
| D5 | Medium | Heading order: party pages start with an h2 (player band title) before h1; Dashboard uses a status pill as h2 and Start Playback as h2 (mockup h2 Player > h3). Settings has only h2 Party, /mods uses h4 under h2. Invite page has 2 h1. | Make player-band title a non-heading (div/p) or lower than page h1; restructure `PartyDashboard` headings; make invite brand a `p.h1`. Files: player band component, `GuestLayout.vue`, Dashboard page. | au json headings problems |
| D6 | Medium | Nickname step (join-nickname frames) not shown with party context: "Create Account" form lacks stepper unless intended party is in session; could not verify end-to-end without OAuth. Button copy "Continue" vs "Continue to the party" in the party variant is per code. | Verify manually with real OAuth; consider showing the party variant also when joining from a code. `resources/js/pages/Users/Signup.vue`. | built/40-home-join-join-nickname-*.png |
| D7 | Low | Contrast: Owner badge 4.36, User badge 4.19, Nothing-playing pill 4.47, Spotify success buttons 2.62. | Darken badge text tokens (`bg-blue-lt`/`bg-secondary-lt` text) and `.btn-success` text/background; `.mp-state.is-idle` colour. | au/_-owner-light.json, au/_spotify-owner-light.json |
| D8 | Low | 403 copy "You can't open Dashboard" (mockup "the Dashboard"); phone 403 breadcrumb collapses to Home / ... / Not allowed (mockup Home / Not allowed). | `app/Exceptions/ErrorPageResponder.php` PAGE_LABELS (use "the Dashboard" form), breadcrumbs() omit party crumb for 403. | built/41-errors-e403-phone.png |
| D9 | Low | Code field does not trim whitespace on paste (" play " -> " PLA" error). Home party row shows track title without artist (mockup "Title, Artist"). | `PartyJoinForm.vue` strip whitespace on input; party list state detail include artist. | crit.mjs output |
| D10 | Info | Resolved by #450 (Discord and Steam sign-in seeded; frames recaptured). Was: Discord button missing on login/anon/join frames: no provider enabled in local env; Not a code defect. | Enable a fake social provider in the harness seed. | built/40-home-join-login-*.png |

---

## Fixed in #438
Page-head row layout, badge/button contrast, 44px touch targets (phone), row-menu clipping, skip link, 'From Fallback Playlist' wording, heading order, Search h1 size, join-code whitespace, party-not-found blank page (+ guest case), 403 copy, built-in theme delete 403, Create Party redirect/validation. Built captures predate these fixes.

## Follow-ups (sub-issues of #414)
- #440 Owner Queue: scheduling actions (Schedule, Plays-at pill, phone overflow) and desktop schedule dialog
- #441 Dashboard: Up Next deck, conditional Start Playback panel, band line, join URL
- #442 Dashboard Needs attention: labelled Approve/Reject, who+why line, Open Flagged Requests
- #443 Queue: permanent skeleton loader card under a short list
- #444 Admin users: detail (Parties card, linked-accounts columns), list, delete impact summary, edit extras
- #445 Admin Settings: sticky unsaved bar, label casing, provider status and Steam form, branding previews; Mods page; Themes page
- #446 Profile, Edit profile, Spotify and unlink pages: restyle to the 42-profile mockup
- #447 Moderation and AI review pages: phone search overlap, header layout, rules and cards
- #448 Create Party step 3: pickers, defaults and done state
- #449 Web Player phone status row, TV join URL decision, no-close-matches copy
- #450 Review harness: seed data and stack gaps so remaining mockup frames can be reproduced

All labelled coding + needs-triage, native sub-issues of #414, no ready-for-agent.
