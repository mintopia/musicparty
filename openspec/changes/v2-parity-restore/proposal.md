# Proposal

## Why

Two triage rounds on 2026-10-10 and 2026-10-11 (`musicparty/2026-10-10-v2-parity`, 98 findings, and `musicparty/2026-10-11-gap-decisions`, 11 decisions) compared v3 with v2. The user ruled that every v2 feature comes back unless it is on the "keep v3" list, and that the v3 UI must look like v2. The admin and profile parity work is already merged on `feature/v3-rewrite` (97eaf55, 39e9743). This change covers everything that is still Partial or Open in the 2026-10-12 status check.

The worst gaps block real use. A Host cannot go Live from the web UI because nothing sets the Fallback Playlist. A Host cannot link Spotify from the UI, and the link lacks the playback scopes the Polling Player needs. Every confirmation and error flashed by about 30 controller actions is silently dropped. Signup ignores the admin's privacy policy URL. The Now Playing header has no backdrop or progress bar and appears only on the Queue and History sections. Several decisions also reverse the archived v3 proposal: the Spotify Search account, the trust score and Whamageddon Mods, email account merge, the Previous control, auto-join, queue paging and the operator commands all come back.

## What Changes

- **Host setup and go-live.** A restored "Link Spotify Account" page where the Host links a control account (now with playback scopes) and, when the admin enables it, a Spotify Search account, then returns to where they started. The Host can unlink either. The Party settings page gains Fallback Playlist and History Playlist pickers (Host's playlists or a pasted playlist), the Player kind (v3's form of v2's polling toggle) and, for the Polling Player, a playback device picker. There is no forced playback. feat-55, feat-48, feat-49, feat-40, diff-12, diff-8, dec-6
- Spotify search uses the Host's Spotify Search account when linked, else the instance client credentials. The Spotify rate-limit backoff is kept per Spotify client application, not per instance (ADR-0028 amends ADR-0021). diff-8
- **Flash messages.** One flash convention (`success`, `error`, `info`, `warning`) across all controllers and pages, shown on the login page too, with the v2 wording restored. consolidated WP3
- **Identity.** A new social login whose email matches an existing user's linked account joins that user (ADR-0027). **BREAKING (data)** Nicknames become unique ignoring case, at most 32 characters; a migration renames duplicates first. Signup uses the admin's privacy policy URL, needs consent when either a terms or a privacy URL is set, and matches the v2 page. Login shows the site name and logo. Opening a valid Party link, QR code or the default Party joins the user automatically. dec-4, dec-5, dec-9, consolidated WP4, ui-L1, ui-L3, ui-L4
- **Now Playing and Party shell.** A v2-faithful Now Playing header (blurred album art, progress bar with elapsed and total time, net rating, icon controls) on every Party page, a working mobile mini-player expand, and the party header shown only where it belongs. A Previous control in the API, Players and UI. feat-2, ui-Q1, ui-Q2, ui-Q3, ui-Q4, ui-Q10, ui-Q11, ui-P5, ui-X9, dec-2
- **Navigation.** v2's per-Party menu (Queue, Search, History/Songs, Users, Moderation, Pending, Party Log, Settings, Theme, TV Mode, Web Player) with role-aware entries, correct breadcrumbs, and a guard on the Browser Player page. feat-3, ui-H3, diff-16, ui-G1, ui-G7, feat-34, ui-X8
- **Party settings and creation.** Every Party setting editable on a sectioned v2-style settings page (name, downvotes, downvotes per hour, raffle selection with its hint, request rules, QR toggle), a Player Tokens page, and a Create Party form with the v2 fields and the Spotify link prerequisite. A `show_qrcode` setting (default on) hides the TV QR code. feat-5, feat-6, feat-33, feat-41, feat-42, feat-45, feat-46, feat-47, dec-8, ui-X3, ui-X7
- **Queue and moderation.** Staff remove any queued Request from the list. Blocklist entries can be edited and deletion asks for confirmation; `*` wildcards and regular expressions also work on identifier and ISRC entries. The Queue shows 20 Requests and loads more on scroll, and duplicate detection stays exact by Track id and ISRC. Inline queue search, filled vote icons, v2 row size, weighted-mode heading and hint, heart icon in upvote-only mode, centred vote errors, session keep-alive, readable Party Log and Pending layouts. feat-9, feat-30, ui-Q5, ui-Q6, ui-Q7, ui-Q9, dec-10, ui-X4, ui-X5, consolidated WP7 and WP10
- **Search.** Paged results, popularity stars, a Spotify link per result, eligibility with a reason before clicking, voting from results, and v2 empty-state wording and input styling. feat-10, ui-S2, ui-S3, ui-S5, consolidated WP9
- **Songs and members.** Song detail page (Votes, Ratings, other appearances, Moderate shortcut, View on Spotify). History gets sortable columns, page size, links to song detail and an "unsent" view. Members list gets search, role filter, sort, paging and a Joined column, plus a member detail page with vote history. feat-18 to feat-26, diff-9, ui-P1 to ui-P4, ui-X6
- **Mods.** Trust Score and Whamageddon ship as first-party Mods using the existing extension points (ADR-0030). diff-1, diff-3, dec-3
- **Operator tools.** `party:*` Artisan commands adapted to v3 (check, fallback, push update, update state, refresh tokens, calculate trust), console log mirroring while those commands run, and scheduled proactive refresh of Host tokens. dec-11, diff-17
- **Theming and visual parity.** The Party Theme applies on every guest page of that Party, not only the TV. The icon set is deduplicated and v2's icons restored. Home, admin controls and TV details are aligned with v2. consolidated WP11, ui-G9, ui-H2, ui-X1, ui-T2, ui-T3
- **Platform.** The Admin API matches the admin UI (users, linked accounts, mods catalogue). consolidated WP12

### Non-goals (keep v3 as is)

- One Instance Theme. There is no multi-theme library or navigation colour (diff-14, dec-1, ADR-0026).
- YouTube playback (diff-2).
- librespot and "simple" webhooks (diff-4).
- Self-service personal API tokens (diff-5, ADR-0005).
- Raw CSS in themes (diff-6).
- Telescope (diff-7, ADR-0020).
- Co-hosts or multiple owners. A Party has one Host plus Moderators (dec-7, diff-10, ADR-0031).
- Forced playback and `party:force` (dec-6, ADR-0032).
- Similar-name duplicate detection (dec-10).
- Running an OAuth server with Laravel Passport (ADR-0005, ADR-0029).
- AI track suggestion or Fallback generation Mods.

### Already done, recorded here only

- Laravel Passport login (feat-80, diff-13) is in the catalogue and working. ADR-0029 and the `membership` and `admin` deltas record it.
- Impersonation (feat-65, diff-11, ADR-0025), admin pages, profile pages, the Inter font, the cover image, the Pulse resolver.

## Capabilities

### New Capabilities

- `web-ui`: the Inertia web shell. Covers flash messages, the Party menu and breadcrumbs, the Now Playing header on every Party page, the mobile mini-player, login and signup screens, and session keep-alive.

### Modified Capabilities

- `membership`: login by Laravel Passport, email account merge, auto-join, unique nicknames, consent with a privacy URL, one Host per Party, member list and member detail.
- `music-provider`: Spotify Search account for catalogue search, paged search, the Link Spotify Account page and its scopes, unlinking, backoff per client application.
- `players`: Previous control, Polling Player device selection, no forced playback, Player kind change from settings.
- `party-lifecycle`: Create Party fields and the Spotify prerequisite, every setting on the settings page, the `show_qrcode` setting, Player Token management page.
- `requests-queue`: Blocklist wildcards, regex on all match types and editing; staff removal from the list; queue paging; exact duplicate detection; song detail; search results with eligibility and voting; history sorting.
- `mods`: Trust Score Mod and Whamageddon Mod; "Future Mods are non-goals" narrowed to AI suggestion.
- `theming`: no theme library; QR toggle on the TV screen.
- `admin`: Laravel Passport and Spotify Search in provider settings.
- `operations`: operator `party:*` commands, console log mirroring, proactive token refresh.

## Impact

- **Code**: Identity (`ResolveSocialUser`, signup and profile requests), Music (`SpotifyLinkController` to a user-scoped link flow, a Spotify Search provider, `SpotifyApi` backoff keying, search paging), Playback (`Control::Previous`, device targeting in `SpotifyPlaybackClient`, Soloist `previous` command), Party (settings, create, `show_qrcode`), Queue (Blocklist matching, song and member queries), Mod (two new Mods), Console (six commands, log mirroring trait). Frontend: `AppShell`, `SidebarNav`, `MobileTabBar`, `NowPlayingBanner`, `MiniNowPlaying`, `PlaybackControls`, `QueueList`, `SearchPanel`, `PlayedHistory`, `Icon`, and the Party pages `Show`, `Settings`, `Create`, `Members`, `Blocklist`, `Pending`, `Log`, `Tv`, `Player`, plus new `Song`, `Member`, `PlayerTokens` and `Spotify` pages. Login and Signup.
- **Database**: unique case-insensitive nickname (dedupe migration first), a `linked_accounts` normalised email lookup column with an index, `parties.show_qrcode` (default true), `parties.playback_device_id` and device name, trust score storage for the Trust Score Mod, and the `spotifysearch` social provider row.
- **APIs**: `previous` playback control; device list; song detail, member detail and member vote history; paged search with eligibility; paged queue; blocklist edit; admin parity endpoints. OpenAPI regenerated. AsyncAPI gains `show_qrcode` in the public Party payload and the Whamageddon Decoration in Queue payloads.
- **Dependencies**: none new. The trust calculation uses a small in-house solver rather than v2's NumPHP (design D9).
- **Docs**: ADR-0026 to ADR-0032; ADR-0004, ADR-0005 and ADR-0021 amended; GLOSSARY entries for Spotify Search account, Trust Score and Whamageddon; README operator commands.
