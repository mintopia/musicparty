# Design

## Context

See proposal.md for why. The current state is `feature/v3-rewrite` at 39e9743. Admin parity and profile pages are merged. The status check of 2026-10-12 lists what is Partial or Open, with evidence for each.

These constraints shape the approach:

- Bounded contexts own their models and writes (ADR-0012), and the architecture test enforces it. A Mod that changes a Request goes through a Queue Action.
- Queue broadcasts carry full state that clients apply (ADR-0018). The Queue, Party state and now-playing payloads are built by one presenter shared with the API.
- Every UI capability needs an equivalent `/api/v1` endpoint with the same authorisation (api spec, "Capability parity"). Every route is in the Scramble OpenAPI export, and every broadcast is in AsyncAPI. CI enforces both.
- Tests run on in-memory SQLite only (ADR-0024). Schema changes must work on MariaDB and SQLite.
- `tests/Feature/ConsoleLoggingTest.php` asserts that logging under an artisan command writes nothing to stdout (review fix code-L11).
- The UI must match v2 (project rule). The references are `docs/design/v2-reference/*.png` and `/home/workspace/musicparty/preview-screenshots/v2-full-*.png`. The v2 source for behaviour is `/home/workspace/musicparty/musicparty-v2`.

## Goals / Non-Goals

**Goals:**
- Make a Spotify Party usable end to end from the web UI: link, pick a Fallback Playlist, go Live, control playback.
- Restore every v2 feature the user ruled back in, adapted to v3's domain model rather than copied.
- Keep v3's correctness guarantees: one serializer, after-commit broadcasts, per-Party ticks, the shared backoff and the architecture rules.

**Non-Goals:**
- See proposal.md, "Non-goals (keep v3 as is)".
- No new Mod extension point. If a Mod needs one, that is a separate ADR.
- No new Composer or npm dependency (CLAUDE.md requires approval). The trust calculation is implemented in-house.
- No v2 URL compatibility. Routes stay v3-shaped.

## Decisions

### D1. One flash convention (consolidated WP3)

Controllers flash only `success`, `error`, `info` and `warning`, the keys `HandleInertiaRequests` already shares. The 28 `successMessage`/`errorMessage`/`infoMessage` sites change, and the per-page `flash.successMessage` readers in Settings, Members, Mods, Pending and Blocklist are deleted in favour of the shell's `FlashAlerts`. The login layout renders `FlashAlerts` too. An architecture test fails when any PHP file under `app/` passes one of the old keys to `with()`, or when any Vue file reads `flash.` keys other than the four.

The alternative was to share the old keys as well. It was rejected because two conventions would drift again.

### D2. User-scoped Spotify linking and the Link Spotify Account page (feat-55, diff-8, consolidated WP1)

The party-scoped `parties/{party}/spotify/link` route is replaced by user-scoped routes: `GET spotify` (the page), `GET spotify/link/{account}`, `GET spotify/link/return` and `DELETE spotify/link/{account}`, where `{account}` is `control` or `search`. This matches how `LinkHostAccount` already stores the link: one linked account per user and provider, not per Party.

The page has v2's two cards, Control and Search. The Search card appears only when an admin has enabled Spotify Search. Under the cards is a Create Party button that is enabled once the required links exist. A `return` query parameter is stored in the session and must be a same-origin relative path. Anything else falls back to `/spotify`, which prevents an open redirect.

Who may link: users with the create-party role, admins, and the Host of any Party. This keeps "only a Host links" in spirit while allowing a link before the first Party exists, which the Create Party prerequisite needs.

Control scopes are the four playlist scopes plus `user-read-playback-state` and `user-modify-playback-state`. A control account linked before this change, without those scopes, is detected from the stored scope string. Add a `scopes` column to `linked_accounts`, filled from the token response. Such an account is shown as "Re-link needed for playback" on the page and in the device picker.

Unlinking uses the existing behaviour from the music-provider spec: delete the credentials, then Pause dependent Parties with a Party Log entry. Search unlink pauses nothing.

### D3. Spotify Search account (diff-8, ADR-0028)

`spotifysearch` is a `SocialProvider` row with `supports_auth = false`. The catalogue entry and the seeder add it, and it appears in admin provider settings. It is never listed by `ListLoginProviders`. Its client id and secret come from its own provider settings.

A Search link is a `LinkedAccount` under that provider. It uses the same encrypted tokens and `HostAccountTokens` refresh, parameterised by the provider's credentials.

`SpotifyMusicProvider::search` resolves its credential through a small `SearchCredentials` resolver in the Music context. The order is v2's: the Party Host's Search account if it is usable, then the Host's control account if it is usable, then the instance client-credentials token (for Hosts with no Spotify link, such as Soloist Parties). The Search link is optional everywhere, including Create Party, even when an admin has enabled Spotify Search (resolved question 5).

The shared backoff (ADR-0021, amended by ADR-0028) is keyed by a hash of the client id, so the Search application and the control or instance application back off independently. When a Search account refresh fails with `invalid_grant`, the account is marked `needs_relink`, search falls back to the control account, and the Host sees a warning on the Party page. Without a Search link, guest search shares the control application's quota and backoff, as in v2; linking a Search account is how a Host separates them.

The alternative was to keep instance credentials only, as in the archived proposal. The user overruled it.

### D4. Account merge by email (dec-4, ADR-0027)

`linked_accounts` gains `email_normalised` (string, 191, nullable, indexed). It holds `mb_strtolower(trim(email))` and is set wherever `email` is set. A migration backfills it.

In `ResolveSocialUser`, the lookup order is:

1. A linked account for the same provider and external id.
2. Otherwise, if the remote email is non-empty and the provider does not report it as unverified, the distinct `user_id`s among linked accounts with that `email_normalised`.

If exactly one user matches, the new linked account is attached to that user. A suspended user raises the existing suspended refusal. With more than one match, the code logs a warning and creates a new user.

Each social provider class gets `emailVerified(SocialiteUser $remote): ?bool`. It reads `email_verified` for Google and `verified` for Discord from the raw user, and returns `null` (unknown) otherwise. `false` blocks the merge. `null` allows it, per the user's ruling that providers generally verify.

The merge raises a `SocialAccountAutoLinked` Identity event. An Admin listener writes the audit entry `account.auto_linked` (ADR-0012: Identity does not write Admin models). The callback flashes `info` "Your {Provider} account was added to your existing account".

Explicit linking from the Profile page (`StartAccountLink`/`CompleteAccountLink`) is unchanged and does no email comparison.

### D5. Nickname uniqueness (dec-5)

`users` gains `nickname_key` (string, 32), maintained by a model mutator as `mb_strtolower(trim(nickname))`, with a unique index. This works the same on MariaDB and SQLite, unlike a functional index.

The migration runs in this order:

1. Backfill keys.
2. For each duplicate key, keep the user with the lowest id and rename the others to `"{base} {n}"`, cut so the result is at most 32 characters, with `n` counting from 2 until unique.
3. Add the unique index.

Each renamed user gets an admin audit entry. A `UniqueNickname` validation rule (ignoring the user's own id) is used by `SignupRequest`, `UpdateProfileRequest` and the admin user edit request, all with `max:32`. `Signup.vue` gets `maxlength=32`. A race between two signups is resolved by the unique index: the loser gets the validation error, using the same pattern as `ResolveSocialUser`'s retry.

### D6. Auto-join (dec-9)

The web Party show action handles a non-member who is logged in with signup complete by calling the existing `JoinParty` action under the same `party-join` rate limiter, then rendering the page. When the limiter refuses, it falls back to today's redirect to the join form with the code prefilled.

An anonymous visitor goes through login with the intended URL kept, so they come back and are joined. The default-Party redirect from Home lands on the same action. The TV route and public API are untouched.

A GET that creates a membership is acceptable here. The effect is idempotent and low-impact, and needs the same knowledge as typing the code.

### D7. Party layout and shared Party context (feat-2, ui-P5, feat-3, consolidated WP2, WP6, WP11)

A persistent Inertia layout, `PartyLayout`, wraps every page under `parties/{code}`: Show and its sections, Settings, Theme, Members, Member, Song, Blocklist, Pending, Log, Stats, Mods, Player Tokens and Player.

`HandleInertiaRequests` adds a lazily evaluated `partyContext` prop whenever the route has a Party and the user may view it. The prop holds:

- the Party summary
- the now-playing payload from the shared presenter, adding `started_at`, `position_ms`, `paused` and `net_rating`
- the viewer's role
- a server-built `menu`, computed through Policies so role-awareness is authoritative
- the Party Theme CSS

The layout owns the public-channel subscription for now-playing and Party state, so every page updates live. Pages stop mounting `NowPlayingBanner` themselves. The progress bar ticks client-side every second from `started_at` and `position_ms`.

The Party Theme CSS is injected by the layout, which fixes WP11 for every Party page, not just the TV.

The alternative was to pass props per controller. That was rejected because 14 controllers would each need it and the gap would come back with every new page.

### D8. Previous control (dec-2)

`Control::Previous` is added. The web and API routes accept `previous`, and `Player::previous()` joins the contract.

Rule: if `position_ms >= 3000`, restart with `seek(0)`. Otherwise go back to the previous Track:

- The Polling Player calls Spotify `POST /me/player/previous` on the chosen device.
- The Soloist Player sends Soloist's previous-track command. Soloist's own UI sends `skip_prev` (`musicparty-soloist/src/web-vue/pages/Landing.vue`, `components/MiniPlayer.vue`), and its proxy relays Music Party commands in the same `{type: "command", command: ...}` frame shape (`src/proxy.ts`), so `skip_prev` is the expected name. Task 6.1 starts by confirming where Soloist handles relayed commands and that it accepts `skip_prev` (open question 1).
- The Browser Player stays unsupported (existing spec).

A replay does not count as a Play anywhere (resolved question 3). The chosen model is to record nothing: no Play row, no flag, no schema change. Before sending, the coordinator stores a short-lived marker in the cache: Party, the most recent Play's id and Track, 30 seconds. When the next observed Track change matches the marker's Track, the coordinator points the Party's now-playing state back at that existing Play (so Ratings during the replay land on it) and writes nothing else: it does not create a Play, advance the Queue, re-lock Up Next, consume the hand-off, append to the History Playlist or touch the no-repeat time. When the replay ends, the Up Next hand-off proceeds as normal. Without the marker, an unexpected Track is handled as today.

The alternative, recording a Play flagged `replay` and excluding it in History, Stats, Party Export, no-repeat and History Playlist append, was rejected: it needs a column and an exclusion in five places, each a chance to miss one. The interrupted Track's Play was recorded when it started and stays, as for a skip.

### D9. Trust Score Mod (diff-1, ADR-0030)

The Mod lives in the Mod context with its own table, `mod_trust_scores` (`party_id`, `party_member_id`, `score`, `calculated_at`, unique per member).

Its hourly Scheduled action, and `party:calculatetrust`, build a weighted directed graph among the Party's Members. The weight of edge A→B is the sum of A's positive Votes on B's Requests plus A's positive Ratings on Plays of B's Requests, within the window. Trust is then personalised PageRank anchored on the anchor Member: damping 0.85, teleport to the anchor, power iteration until the L1 change is below 1e-6 or 100 iterations. The result is normalised so the highest score is 1.

v2 solved a linear system with NumPHP. That needs a new dependency and can be singular. PageRank is the standard formulation of EigenKarma and always converges (resolved question 4).

Settings:

| Setting | Default |
|---|---|
| anchor Member | the Host |
| window in days | 7 |
| Score weighting | off |
| maximum bonus | 2 (integer 0 to 10) |

When weighting is on, the Score Modifier adds `round(max_bonus × requester score)`. System Requests get 0.

Trust is shown to the Host and Moderators through a new core slot, `members.columns`, on the Members list and the member detail page. This uses the existing "Build-time Vue slots" extension point, with a new slot name rather than a new point. Trust is never in a public payload or Decoration.

### D10. Whamageddon Mod (dec-3, diff-3, ADR-0030)

Settings:

| Setting | Default |
|---|---|
| Track (a Spotify Track id or link) | Wham!, "Last Christmas"; the implementer confirms the current Spotify id |
| label | "Whamageddon" |
| hourly bonus | 1 |
| re-add interval | 2 hours |
| quiet hours | 02:00 to 10:00 |
| hold-from hour | 23 |

Times use `config('app.timezone')`.

The Mod keeps per-Party state (the current Request id and its accumulated bonus) in its own table, `mod_whamageddon_states`.

Enabling creates a system Request through the Queue's system-Request Action. Disabling removes it through the Queue's remove Action, attributed to the Mod.

The hourly Scheduled action behaves as follows:

- Outside the quiet hours, if the Request is Queued, it adds the bonus to the accumulated value. The Score Modifier supplies that value.
- If the Request has played and the interval has passed, it creates a new system Request.
- From the hold-from hour, it sets `not_before` to the end of the quiet hours.
- After the quiet hours, it clears `not_before`.

`not_before` is set through a new Queue Action, `SetRequestNotBefore`, because of the ADR-0012 context rule. The Request carries a Decoration badge with the label and an allow-listed accent. This replaces v2's `css_classes`.

The system Request passes the Blocklist and the Track rules, but not per-Member limits, because it has no requester. A refusal is logged once per run (resolved question 6, accepted default).

### D11. Polling Player device and Player kind from settings (feat-40, diff-12, dec-6, ADR-0032)

`parties` gains `playback_device_id` and `playback_device_name`, both nullable. `GET /api/v1/parties/{party}/player/devices` and its web counterpart call `SpotifyPlaybackClient::devices()` (`GET /me/player/devices`). `PUT parties/{party}/player` accepts `device_id` (and `null`) besides `kind`.

`SpotifyPlaybackClient` passes `device_id` on play, pause, next, previous, seek, volume and queue calls when one is set. A Spotify 404 "Device not found" maps to a conflict error naming the device, and a `player.device_missing` Party Log entry at most once a minute.

There is no `force` column, no job and no command (no forced playback). The Player kind select on the settings page uses the existing `ChangePartyPlayer` Action.

### D12. Settings, create and Player Tokens pages (feat-5, feat-6, feat-33, feat-41, feat-42, feat-45, feat-46, feat-47, feat-48, feat-49)

`Party/Settings.vue` is rebuilt as v2's sectioned form: General, Playback, Requests and Restrictions, Display, Mods. Each section saves through web routes that call the same Actions and Form Requests as the API: `UpdatePartyRequest`, `SelectPartyPlaylists` and `ChangePartyPlayer`.

The playlist pickers call the existing playlists endpoint for the Host's playlists. They also accept a pasted `open.spotify.com/playlist/...` link, a `spotify:playlist:` URI or a bare id. These are parsed server-side by a `PlaylistReference` value object.

`show_qrcode` is a boolean `parties` column, default true. It is part of the public Party payload, so the TV hides the QR code live. The AsyncAPI schema is updated.

`Party/Create.vue` gets the v2 fields and calls the Spotify prerequisite check (D2).

`Party/PlayerTokens.vue` lists, issues (showing the token once with a copy button) and revokes tokens through the existing API Actions.

### D13. Queue list paging and duplicates (ui-Q9, dec-10)

The Queue state already arrives whole, in props and in `queue.updated` (ADR-0018). Paging is a client-side window. `QueueList` renders the first 20 entries, and an IntersectionObserver sentinel at the end raises the window by 20. Live updates replace the underlying array while the window size and scroll position are kept.

The API `GET parties/{party}/queue` keeps its documented pagination (api spec). Server-side paging of the Inertia prop was rejected, because broadcasts carry the full Queue anyway.

Duplicate detection stays as implemented: exact provider Track id, or equal non-null ISRC. The requests-queue delta now states this, so similar-name matching is a recorded non-goal.

### D14. Search results (feat-10, ui-S3, ui-S5, consolidated WP9)

`SearchPartyProvider` takes `page`, with 20 per page and offset `(page - 1) × 20`. It returns `has_more` when the Provider reports more results.

Each result is enriched in one batch by a new `Queue\Actions\AssessRequestEligibility` Action. It runs the core rules that need no write (requests accepted, Blocklist, explicit, length, no-repeat, per-Member limit) plus Mod Request Rules that declare themselves `cheap`. AI Request Review is not cheap and still runs only on submit. For each Track it returns `{eligible, reason, queued_request_id, my_vote}`. The Blocklist is loaded once per batch, using the existing `Blocklist` loader.

The UI shows popularity as 0 to 5 stars (`round(popularity / 20)`), a View on Spotify link through the public-link builder, a disabled Request button with the reason, and up, down and retract Vote controls for queued results. The empty state is v2's wording: "No results found for your search". The implementer confirms the exact string against the v2 search view.

### D15. Song and member detail (feat-19 to feat-26)

These are the routes:

| Purpose | Web | API |
|---|---|---|
| Song detail | `parties/{party}/songs/{trackRequest}` | `GET /api/v1/parties/{party}/requests/{trackRequest}` |
| Members list | `parties/{party}/members` | the list endpoint gains `search`, `role`, `sort`, `direction`, `per_page` |
| Member detail | `parties/{party}/members/{member}` | `GET /api/v1/parties/{party}/members/{member}` |
| Member votes | | `.../members/{member}/votes` |

The song detail endpoint is extended with `votes`, `ratings` and `appearances` for Members, with Vote and Rating identities shown only to Members of the Party, never to the public. Song detail also shows other appearances: other Requests and Plays of the same Track id or ISRC in this Party. The Moderate action links to the Blocklist with `?type=track_id&value={id}&name={name}` prefill.

History (`ListPlayHistory`) gains `sort` (name, artist, requested, played, score), `direction`, `per_page` (10, 20, 50 or 100), and type `all|played|unsent`, with `all` as the new default. Every list query asserts a constant query count, following the review-fixes 5.4 pattern.

### D16. Operator commands and log mirroring (dec-11, diff-17)

The six `party:*` commands are thin wrappers over existing Actions and jobs:

| Command | Wraps |
|---|---|
| `check` | `FallbackPlaylistGate`, a cache refresh through `forgetPlaylist`, `PlayerHealth`, Host account status |
| `fallback` | dispatches `TickParty` for Live Parties whose last tick is older than 90 seconds |
| `updatestate` | runs `TickParty` synchronously |
| `pushupdate` | re-dispatches the Party state and Queue broadcasts |
| `refreshaccesstokens` | D17, run once |
| `calculatetrust` | D9 |

A `MirrorsLogToConsole` trait registers a `MessageLogged` listener only for the duration of `handle()`. It writes through `$this->output` and maps verbosity: normal shows warning and above, `-v` info, `-vv` debug. The listener is removed in `finally`. Global logging is unchanged, so `ConsoleLoggingTest` stays green, and a new test proves mirroring happens only inside these commands. A `PartyCommand` base resolves `{code}` case-insensitively and exits non-zero with an error for unknown codes.

### D17. Proactive token refresh (diff-17)

A `RefreshExpiringHostTokens` job is scheduled every minute with `onOneServer`. It selects linked accounts of the Spotify control and Spotify Search providers whose `access_token_expires_at` is under 5 minutes away and that do not need re-linking. It refreshes each through `HostAccountTokens`, under the existing per-account lock, so a concurrent on-demand refresh performs one refresh. Accounts are processed in chunks of 50.

### D18. Visual parity method

Every UI task names its v2 reference screenshots. The PR attaches v3 screenshots at 1280px and 390px in light and dark, taken by the existing Playwright screenshot script. A reviewer compares them side by side before merge.

"Open*" visual items from the status check, those with no code evidence and not visually verified, are first confirmed in a browser. If one already matches, the task records that with the screenshot and closes with no code change.

## Test seams

| Area | Seam |
|---|---|
| Linking, Search account | `Socialite::fake`-style driver double; `Http::fake` for Spotify token, search, devices and previous endpoints |
| Email merge | provider double returning chosen raw attributes; assert audit event and listener |
| Nickname | Form Request tests plus migration test on a fixture with duplicates |
| Auto-join | feature tests on the show route (member, non-member, anonymous, rate limited, Ended) |
| Party layout | Inertia assertions on `partyContext` for each Party route; Vitest for `PartyLayout`, `NowPlayingBanner` tick (fake timers), `SidebarNav` menu by role |
| Previous | Fake Player and `Http::fake`; coordinator marker test with frozen clock |
| Mods | Mod tests with frozen clock (`travelTo`) for the hourly action, quiet hours and re-add; PageRank unit tests on hand-computed graphs |
| Commands | `artisan()` tests with `expectsOutputToContain` and exit codes |
| Queue window, search, history | Vitest with mocked Echo and router (existing pattern); query-count assertions |

## Task group ordering (drives ticket `blocked_by` edges)

| Group | Blocked by | Why |
|---|---|---|
| 1. Flash messages | none | Every later page relies on flashes showing |
| 2. Host setup and go-live | 1 | Link callback and settings saves flash. 2.2 needs 2.1 (page and routes). 2.4 needs 2.1 (scopes) |
| 3. Party layout and navigation | none | Pages in 5 to 9 render inside `PartyLayout`. 3.2 and 3.3 need 3.1 |
| 4. Identity, signup and login | 1 | Merge and signup messages are flashes. 4.1 to 4.4 are independent of each other |
| 5. Party settings and creation | 2, 3 | Same Settings page as 2.3 and 2.4; new pages live in the layout. 5.2 needs 2.1 (prerequisite check) |
| 6. Previous control | 3 | Header controls live in 3.2 |
| 7. Queue and moderation | 1, 3 | Blocklist and Pending pages read flashes; the Queue renders in the layout |
| 8. Search | 3 | Search section lives in the layout. 8.2 needs 8.1 |
| 9. Songs, history and members | 3, 7 | 9.2 Moderate needs 7.2 (Blocklist edit and prefill) |
| 10. Mods | 9 for 10.1; none for 10.2 | Trust column uses the Members pages from 9.3 |
| 11. Operator tools | 10.1 for `party:calculatetrust` only | 11.1 and 11.2 are otherwise independent |
| 12. Theming and visual parity | 3, 5, 9 | Polish after the structure exists |
| 13. Admin API parity | none | Independent |
| 14. Integration | all | Final checks |

Groups 1, 3 and 13 can start at once. After group 1, groups 2 and 4 run in parallel. After group 3, groups 6, 7 and 8 run in parallel.

## Risks / Trade-offs

- [Email merge allows takeover through a provider that does not verify emails and does not say so] → Merge only with a single match, block on an explicit unverified flag, audit every merge, show the user a message. Admins are merged too (resolved question 1).
- [Auto-join on GET can be triggered by a logged-in user's browser prefetch] → The effect is a Guest membership, which is idempotent and harmless. Link unfurlers are anonymous and only reach login.
- [The Search account adds a second token to refresh, and a second backoff key] → Refresh shares `HostAccountTokens`, and D17 refreshes proactively. Search falls back to the control account, then instance credentials, on any Search account failure.
- [Without a Search link, guest search spends the control application's quota] → Accepted by the user (search link optional). The Host is shown that linking a Search account separates the quotas.
- [Nickname dedupe renames users] → Only later duplicates are renamed. Every rename is audited, and the release notes call it out.
- [A client-side Queue window keeps the full Queue in memory on phones] → It is already the case today (ADR-0018 payloads). The window only reduces DOM nodes.
- [Eligibility for 20 results per search adds queries] → One batch Action, the Blocklist loaded once, and a query-count test.
- [Previous replay detection depends on a 30-second marker] → If the Player reports the replay late, it is handled as an unexpected Track, which is today's behaviour and may record a Play. That is acceptable for a rare control, and the marker test covers the normal timing.
- [Mirroring logs in commands could leak secrets to terminal scrollback] → Logs already avoid tokens (music-provider spec). Mirroring is the same content the log file gets.

## Migration Plan

1. Migrations, all portable, each with a down: `linked_accounts.email_normalised` and `scopes`; `users.nickname_key` with dedupe and the unique index; `parties.show_qrcode` (default true), `playback_device_id`, `playback_device_name`; `mod_trust_scores`; `mod_whamageddon_states`; the `spotifysearch` social provider row through the provider seeder.
2. Deploy. Hosts whose control link lacks playback scopes see "Re-link needed for playback" and re-link once.
3. Admins who want separate search quota create a second Spotify application, enter it under Spotify Search and enable it. Hosts may then link a Search account. Until a Host does, their Party's search uses their control account, else the instance credentials.
4. Rollback: every migration has a down. The nickname dedupe is not reversed: renamed nicknames stay renamed, which is harmless.

## Open Questions

1. **Which command name does Soloist accept for previous?** Expected `skip_prev` (see D8). Task 6.1 confirms it in `/home/workspace/musicparty/musicparty-soloist` before building. If Soloist does not handle a relayed previous command, the Soloist Player declares Previous unsupported and the UI disables it.

## Resolved questions

Answered by the user on 2026-10-12 unless marked "accepted default" (recommendation kept, not raised with the user).

1. **Should auto-merge by email skip admin accounts?** No. Admins are merged too, and every merge is audited and shown (task 4.2).
2. **Does the Soloist protocol support previous?** Still unconfirmed, so it stays open as question 1 above. Soloist's UI uses `skip_prev`. Task 6.1 confirms first.
3. **Does a replay through Previous count as a Play?** No. It does not count in History, Stats, Party Export or the no-repeat window. D8 records nothing for a replay (task 6.1).
4. **What trust formula?** Personalised PageRank (D9, task 10.1).
5. **Is the Search link required for Create Party?** No. Only the control link the Player needs is required, even when Spotify Search is enabled. Without a Search link, search uses the Host's control account (D3, tasks 2.2 and 5.2).
6. **Which rules does Whamageddon's system Request follow?** Accepted default: the Blocklist, explicit filter and length rules, but no per-Member limits. If refused, log once per run and retry next hour (task 10.2).
7. **Is Blocklist wildcard matching case-sensitive for identifiers?** Accepted default: names and ISRCs ignore case. Track, artist and album ids are case-sensitive (task 7.2).
8. **What format for a renamed duplicate nickname?** Accepted default: `"{name} {n}"`, cut to 32 characters (task 4.1).
