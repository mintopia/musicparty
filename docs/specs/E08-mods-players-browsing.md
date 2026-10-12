# E8 Mods, Players & Browsing

Goal: build the shared Request and playback foundations on the E6 seams, then give mods a real contract, make Flagged and Scheduled Requests core, feed pushed state from the Web Player and Encore into `PlaybackSync`, and let members browse albums and artists.

- Status: Draft
- Wave: 7
- blocked_by: E7 UI foundation (and transitively E6 Architecture)
- ADRs: 0001 (Music Provider and Player split), 0002 (Flagged Requests are core), 0003 (in-repo mods, auto-registered behind a Mod contract)
- Builds on: E6 `PlaybackState`, `PlaybackSync::apply`, `ProviderPlayback`, `MusicProvider`/`MusicProviderFactory`, `SpotifyGateway`, `QueueSelector`, `RequestRules`/`RequestRule`, `TrackEligibility`, `ModSettingsStore`; E7 `AppLayout`, `BareLayout`, `TrackRow` (with decoration slot and approval states), `TrackList`, `ConfirmDialog`, `Toast`, `useEcho()`, `useRequestTrack`, `useProgress(PlaybackState)`, the Inertia Search page.

## Problem

### Mods and Requests
- Mods have no contract, registry or discovery. Whamageddon is wired by hand as a service provider (in `bootstrap/providers.php` after E3), with its own `InstallCommand` that seeds `mods`/`mod_settings` rows. The mod code string is repeated as literals.
- The only extension points are clock ticks (`HourTickEvent`/`MinuteTickEvent`/`DayTickEvent`) and `PartyModSetting` model events. `RequestRules` and `QueueSelector` have no Mod hook, and `RequestRule` results are allow or reject only.
- Core columns exist for one mod only: `upcoming_songs.fallback_override` and `css_classes` (Whamageddon), and `parties.trustscore`, `parties.trusted_user_id`, `party_members.trustscore` (Trust).
- Whamageddon bugs: the install defines a `votes` setting but the code reads `score_adjustment` (`Whamageddon.php:138`), so the user setting does nothing; the overnight hold uses server-local hours (`Whamageddon.php:33`, `:62`); the track setting is a raw `spotify_id` (`Whamageddon.php:97`).
- Trust is not implemented end to end. `Party::calculateTrustScores` (`Party.php:713-799`, untouched by E6) calls NumPHP `LinAlg::solve` against a unit vector for `trusted_user_id`, a linear-system solve rather than eigenvector centrality. Singular matrices throw. It runs only via the manual `party:calculatetrust {party}`, and nothing reads `trustscore`.
- There is no way to hold a Request for a manager, or to schedule one for a time.

### Players
- Every playback signal ends in a poll. Polling builds a `PlaybackState` from `ProviderPlayback::currentPlayback`, and Playback Webhooks only trigger that sync (E6 R8). The Web Player's `player_state_changed` listener POSTs to `/webhooks/parties/{code}/simple` and repeats after 2 s, so the SDK state is thrown away.
- The Soloist webhook ignores its payload and polls. Encore can already stream Soloist frames into Reverb, but Music Party does not listen.
- The Web Player page is inline JS in Blade with the access token rendered into the HTML. It has no recovery from `not_ready`, `autoplay_failed` or `authentication_error`, the Play button hides itself permanently, transport buttons are commented out, and every open tab posts a webhook on every track change.
- `PlaybackState.source` has only `Polling` and `Webhook`. Nothing in the UI calls `ProviderPlayback::seek`. All transport goes through the Spotify Web API even when a Player could take the command directly.

### Browsing
- Search covers tracks only. A result shows its album art, album name and artists, but none of them link anywhere.
- E6's `MusicProvider` exposes `getAlbum`, `getArtist`, `getArtistTopTracks` and `getArtistAlbums`, but no page uses them.
- Search uses the Party owner's market. There is no Party country and no per-member limit on search.

## Goals

- Shared foundations first: Player-reported track references, a richer `PlaybackState`, a Player-transport seam, a `QueueSelector` candidate filter with a hook for adding Requests, and three-way eligibility (allow, flag, reject).
- A `Mod` contract with service-provider auto-registration under `app/Mods/<Name>/`, extension points on the E6 seams, and per-Party enablement.
- Flagged Requests and Scheduled Requests as core Request states.
- Whamageddon ported and fixed. Trust researched, signed off, then shipped as a scheduled mod. AI Request Review via `laravel/ai`.
- The Player is whatever sends playback updates. Pushed state from the Web Player and Encore is applied directly, and polling runs only when pushed state is missing or insufficient. Commands go to the Player's own integration first.
- Album and artist pages, reached from search, where every track can be requested with the same eligibility as search.

## Non-goals

- Third-party or Composer-packaged mods (ADR 0003).
- Playback Webhook token auth (later; the E1 per-Party rate limit stays). Per-Party Encore credentials.
- Changes inside Encore, unless Music Party needs them. Those become tickets in the Encore repo.
- Playlists (browsing a provider playlist or choosing the Fallback Playlist), search by album or artist as result types, recommendations, related artists or genres.
- A second real Music Provider. Converting existing Blade pages (E9).
- A Trust implementation before the research is signed off.

## Encore contract (as implemented in Encore `src/relay.ts`)

- Encore dials `wss://<app host>/app/<REVERB_APP_KEY>` (one public hostname after E4) and subscribes to its configured channel. For a `private-` channel it signs the subscription itself: `auth = key:HMAC-SHA256(secret, "<socket_id>:<channel>")`. It does not call Music Party's auth endpoint.
- Outbound: every Soloist frame is sent as a client event `{"event":"client-<frame.type>","channel":"<channel>","data":<frame object>}`. Frames are Soloist's native JSON: `playback_state`, `track_changed`, `playback_changed`, `position_sync`, `volume_changed`, `queue_changed`, `auth_state`, `device_changed`, `context_changed`, `options_changed`, `error`. Track identity is `item.uri` (`spotify:track:...`). Position is `{position_ms, timestamp_ms, speed}`.
- Inbound: any non-`pusher:` event on the channel has its `data` unwrapped and sent to Soloist as a command, e.g. `{"type":"get_state"}`, `play`, `pause`, `next`, `skip_prev`, `seek {position_ms}`.
- Delivery is best effort. Frames are dropped while disconnected, reconnect backs off 0.5 s to 30 s, and Soloist emits a frame about every 10 s while playing. On (re)subscribe the relay does not replay the latest state.
- Known limitation: the app secret lets Encore subscribe to any private channel, so only the operator should run it. This will most likely be revised later.

## Requirements

### Shared foundations
1. `MusicProvider` gains a method that turns a Player-reported track reference (for Spotify, a `spotify:track:` URI) into a `provider_track_id`, returning null for anything it does not recognise. The Spotify implementation keeps URI parsing inside `App\Services\Music\Spotify` (E6 R12); the Fake implements it for tests.
2. `PlaybackState` gains `WebPlayer` and `Encore` source cases, an optional list of upcoming track ids, and an optional connection status (connected, logged out, error). Polling and Playback Webhook states leave the new fields empty.
3. A Player-transport seam: play, pause, next, previous and seek go to the current Player's own integration when it supports the action (Soloist commands through Encore, the Web Playback SDK in the Web Player). The Spotify Web API through `ProviderPlayback` is used only for actions those integrations do not support, or when neither is the current Player. `Api\V1\PartyController::control` and `party:force` go through the seam.
4. `QueueSelector` gains a candidate-filter step before the effective-Score step, and a hook through which core features and mods add Requests to the Queue. Flagged Requests and Requests whose hold time has not passed are filtered out there.
5. `RequestRule` returns allow, flag or reject, each with a reason. `RequestRules` runs core rules, then mod rules, then AI rules last. A reject stops later stages. Each rule is told whether it runs on Request submit or on `TrackEligibility` annotation.
6. `TrackEligibility` returns the three-way outcome. `TrackRow` (E7) shows "needs approval" for a track that would be flagged and "awaiting approval" for the member's Flagged Request.

### Core Request features
7. Hold time and score adjustment are core Request concepts: `upcoming_songs.not_before` and `score_adjustment` stay on the core table and any feature or mod can set them.
8. **Flagged Requests.** A Request can be Flagged. A Flagged Request is not in the Queue, cannot be voted on, and is never selected.
9. Flagging is a per-Party opt-in setting, off by default. When it is off, a flag outcome is treated as allow.
10. A Flagged Request counts toward its requester's request limit.
11. A Blocklist rule can be set to flag instead of reject. Existing rules keep rejecting.
12. Party managers see a Flagged Requests approval queue (Inertia, on `AppLayout`, built from `TrackList`/`TrackRow` and `ConfirmDialog`) with the reason. Approve puts it in the Queue; reject removes it and tells the requester why. Managers are notified in real time on a private manager channel via `useEcho()`.
13. The requester sees their Request as awaiting approval and can withdraw it, which removes it and frees its place in the request limit.
14. **Scheduled Requests.** A Party owner or moderator can make a Scheduled Request: a Request that plays as soon as possible after a given time in the Party timezone. Other members cannot.
15. A Scheduled Request is held (requirement 7) until its time. Once the time has passed it is selected before any unscheduled Request; when several are due, the earliest time goes first.
16. Scheduled Requests show their time in the Queue and can be cancelled by an owner or moderator.

### Mod framework
17. A `Mod` contract: each mod has a directory `app/Mods/<Name>/` with a manifest class that declares its code, name, description, settings and the extension points it implements.
18. Each mod has a service provider that is discovered and registered automatically. No mod is listed in `config/app.php` or `bootstrap/providers.php`, and there is no per-mod install command.
19. A sync command, run from the container entrypoint on deploy, keeps the `mods`/`mod_settings` rows in line with the discovered manifests. A mod whose directory is removed is disabled everywhere and its settings are kept.
20. Each Party enables mods individually. A disabled mod has no effect on that Party. Enabling or disabling gives the mod a hook to react (Whamageddon adds or removes its Request), replacing the `PartyModSettingUpdatedEvent`/`DeletedEvent` listeners.
21. Typed settings: boolean, integer, string, track, time of day and encrypted secret. Each has validation, a default and visibility (Host-editable or instance-only). All reads and writes go through `ModSettingsStore`. A track setting stores a `provider_track_id` on the Party's Music Provider.
22. Extension points: request rules (requirement 5), score modifiers on `QueueSelector`'s effective-Score step, Queue additions of system Requests (no requester) through the requirement 4 hook, decorations (display name, label, CSS class, accent) rendered through `TrackRow` and the legacy Queue, Up Next and TV views, and scheduled actions with a frequency, run per Party with the mod enabled. One Party's failure is logged and does not stop the others.
23. No mod-only column on a core table. `fallback_override` and `css_classes` and the Trust columns move to mod-owned storage with a data migration, and every existing Party's mod state survives.
24. Mod enablement and settings move from the Party form (`mods_{mod}_{setting}` fields) to a per-Party Mods screen in Inertia. Instance-only settings are on an admin Mods screen. Both use `AppLayout`.
25. A new instance-level timezone setting on the admin settings page, defaulting to `config('app.timezone')`. A Party timezone setting on the Party form, defaulting to the instance timezone. Time-based mods and Scheduled Requests use it.

### Whamageddon
26. Ported to the mod layout with current behaviour: it keeps the configured track in the Queue as a system Request, adds Score each hour until it plays, re-adds it 2 hours after it played, holds it from 23:00 until 10:00, skips the hourly bump between 02:00 and 10:00, and shows the configured display name and CSS class as a decoration. All times use the Party timezone.
27. The hourly bump uses the configured setting. The `votes` vs `score_adjustment` mismatch is resolved to one setting, migrating existing values. The track setting migrates from `spotify_id` to a track setting.

### Trust (Eigenkarma)
28. A research ticket (Opus) comes first. It analyses the intended algorithm and the NumPHP code, and produces a short design for the user's sign-off: what Trust means, what it affects, behaviour for small or no-vote Parties, whether eigenvector centrality is the right model, and whether NumPHP stays.
29. After sign-off, Trust is a mod with a scheduled action that recalculates per Party, never throws on degenerate input, and has the effect the design specifies through the extension points. `Party::calculateTrustScores`, `party:calculatetrust` and the trust columns move into the mod.

### AI Request Review
30. A mod contributing an AI-stage `RequestRule` that sends the Request to a model through `laravel/ai`, with Jev (typesafe) and OpenAI-compatible drivers. Per-instance config: endpoint, auth (encrypted) and model. Enabled per Party.
31. It runs only on Request submit, never during `TrackEligibility` annotation.
32. It returns allow, flag or reject with a reason, shown to the requester (reject) or managers (flag). When the AI is unavailable (timeout, error, unparseable answer) it returns allow, so the earlier stages decide.

### Player model
33. The Party has no Player setting. Polling on/off (`poll`) is the only playback setting. `force` and the device picker keep their current behaviour.
34. Music Party records, per Party, the source and receive time of the last applied `PlaybackState`. The Host sees the current Player on the Party page.
35. Pushed state is fresh while `WebPlayer` or `Encore` states keep arriving within a staleness window. While fresh, the `PartyUpdate` poll loop and `party:fallback` do not call `ProviderPlayback::currentPlayback`. When it goes stale and Polling is on, polling resumes within the 90 s fallback window or sooner. When Polling is off, nothing polls.
36. Pushed states are applied only through `PlaybackSync::apply`, under the E5 per-Party lock. A state whose `sampledAt` is older than the last applied one is dropped.
37. Only the playing client emits state. If the Web Player and Encore both send, the latest `sampledAt` wins.
38. Pushed state arrives as client events (whispers) on private channels. Music Party consumes them through Reverb's server-side `MessageReceived` event. The listener only parses channel and event name and hands the payload to a queued job; it does no Spotify or database work in the Reverb process. Frames on unknown channels or Parties, or with malformed data, are dropped and logged at debug level.
39. The broadcast Party state carries `progressMs` and `sampledAt`. The legacy `Player.vue`/`TvPlayer.vue` interpolate from `sampledAt`; Inertia screens use `useProgress(PlaybackState)`.

### Web Player
40. The Web Player is an Inertia page on `BareLayout`, opened from an "Open Web Player" button in a dedicated popup window. Members with the `owner` role on the Party and instance admins can open it; nobody else.
41. It maps each Web Playback SDK `player_state_changed` (track, paused, position, duration, timestamp) to a `PlaybackState` with `source = WebPlayer` and `sampledAt` from the SDK timestamp, and sends it as a whisper on the Party's private Web Player channel, following the Encore pattern. It never triggers a poll to learn what is playing.
42. The access token is never rendered into HTML. The page fetches it from an authenticated endpoint backed by `SpotifyGateway`. Refreshed tokens reach every user allowed to open the Web Player (requirement 40) on a private channel through `useEcho()`, and nobody else.
43. It recovers from `not_ready`, `autoplay_failed`, `authentication_error`/token expiry and disconnect: it reconnects, refreshes the token, and shows a "click to resume" control when the browser blocks autoplay.
44. Play, pause, skip and seek work from the Web Player through the SDK, and from the Party page for managers through the transport seam (requirement 3). `Api\V1\PartyController::control` gains `seek`, with inputs validated by `PartyControlRequest`.
45. While it is the active Player, closing it or navigating away shows a confirmation warning.
46. One active Web Player per Party. When a second opens, the newest takes over; the old one is told on the private channel, shows "taken over elsewhere", disconnects from the SDK and stops sending state.

### Encore
47. Each Party has an Encore channel, `private-encore.{code}`. Music Party shows the operator its relay settings (URL, Pusher protocol on, channel, app key). The app secret is shown only to instance admins.
48. `routes/channels.php` refuses user sessions on `encore.{code}`.
49. When Encore (re)connects, Music Party requests a snapshot by broadcasting `{"type":"get_state"}` on the Encore channel.
50. Soloist frames map to a `PlaybackState` with `source = Encore`: `status` to isPlaying, `item.uri` through requirement 1 to trackId, `item.decorations.playback.duration_ms` to durationMs, `position.position_ms`/`position.timestamp_ms` to progressMs and `sampledAt`, `queue_changed` to upcoming tracks, `auth_state`/`error` to connection status.
51. A logged-out `auth_state` or an `error` marks pushed state as insufficient and lets polling take over if Polling is on.

### Playback Webhooks
52. `librespot`, `simple` and `soloist` Playback Webhooks keep their routes and behaviour: each triggers a sync from `ProviderPlayback::currentPlayback` (`source = Webhook`). The E1 rate limit stays. A Playback Webhook does not suppress polling.

### Browsing
53. A Party has a country (market) setting on the Party form. Search and browse use it. Existing Parties are set from the owner's market, so search results do not change.
54. Each member's search and browse calls share one per-member leaky-bucket limit that allows bursts. Over the limit, the page shows a "try again shortly" state rather than an error.
55. `GET parties/{party}/albums/{album}` and `GET parties/{party}/artists/{artist}`, where the ids are provider ids of the Party's Music Provider, use the same middleware and authorization as `parties.search`, including member creation on first view.
56. Search results link the album name and art to the album page and each artist name to its artist page, through `TrackRow` link props and Wayfinder routes.
57. Album page (Inertia): art, name, linked artists, release year, track count, then tracks in disc and track order as `TrackRow`s annotated by `TrackEligibility`.
58. Artist page (Inertia): name and image, top tracks as annotated `TrackRow`s, and a paginated grid of `AlbumRef` cards. Albums and singles are shown; "appears on" is excluded.
59. Eligibility on album and artist pages is identical to search for the same Track, Member and Party state, including every core reason and the three-way outcome. Unplayable tracks are shown disabled with a reason.
60. Requesting uses `useRequestTrack` with `provider_track_id`. The server re-validates with `RequestRules`. On success the row shows its in-Queue state without a reload, and rows reflect Queue changes broadcast while the page is open.
61. Lookups go through `MusicProviderFactory::forParty($party, Purpose::Search)` and are covered by E5's 429 backoff, showing the "try again shortly" state while backoff is active. Album, artist, top-track and artist-album results are cached per country. Eligibility is never cached across Members.
62. An unknown or invalid provider id returns a 404 page in the Party layout. Breadcrumbs run Party, Search (preserving the last query), then Album or Artist, and the back button returns to the search results intact.

## Acceptance criteria

- Pest tests with `FakeMusicProvider` cover each extension point through `RequestRules` and `QueueSelector`, enablement isolation, the core → mod → AI order with reject short-circuit, and that the AI rule is not called during annotation.
- Adding `app/Mods/<Name>/` with a manifest and service provider makes the mod appear on the per-Party Mods screen with no other code change. No provider file lists a mod; `InstallCommand` is gone. Removing a mod directory and running the sync disables it and keeps its settings.
- A schema check shows no core table column used by only one mod.
- Flagged Request tests: flagging on and off, counting toward the limit, a Blocklist rule set to flag, approve, reject, and withdraw by the requester (and refusal for another member).
- Scheduled Request tests: a member is refused; an owner and a moderator succeed; it is not selected before its time in a non-UTC Party timezone; after its time it is selected ahead of a higher-Score unscheduled Request; two due Scheduled Requests play earliest first.
- Whamageddon tests cover the hourly bump with the configured value, the 2-hour re-add, the overnight hold in a non-UTC Party timezone and the decoration.
- AI review tests cover allow, flag (flagging on and off), reject and unavailable, with `laravel/ai` faked and no network calls.
- Trust implementation tickets are not started until the research ticket is closed with the user's sign-off recorded on it.
- Pest tests feed recorded SDK states and Soloist frames through the mappers into `PlaybackSync::apply` and assert current track, Played Track, Queue top-up and broadcast, recording zero `currentPlayback` calls. Tests prove polling is skipped while pushed state is fresh and resumes after the window, an older `sampledAt` changes nothing, and the latest of two sources wins.
- Transport tests prove a command goes to Encore as a Soloist command when Encore is the Player, to the Web Player when it is, and to `ProviderPlayback` otherwise.
- A user session cannot authorise `private-encore.{code}`. A manager without the `owner` role, a member and a guest cannot open the Web Player or receive its token; an owner-role member and an admin can (`assertInertia`).
- Browser check on the dev deployment: open the Web Player popup, play, pause, skip and seek; open a second popup and see the first show "taken over elsewhere"; let the token expire and see playback continue.
- Manual check with a real Encore on the dev deployment: track changes appear on the Party page within 2 s, with no polling in the logs while it plays.
- librespot, simple and soloist webhook tests still pass unchanged.
- Browsing tests: album and artist pages return the expected component and props for a member; a banned member sees blocked reasons; a guest is redirected to login; an unknown id returns 404; the reasons for the same Tracks are equal across search, album and artist for a fixture Party with Blocklist rules, `max_requests`, `explicit=false` and a recently played Track; a blocked Track posted directly is rejected; with backoff active or the leaky bucket empty, the pages render the retry state and make no provider calls; browse cache keys include the Party country.
- Manual check on the local deployment, from search to album to request to Queue, and from search to artist to album to request, matches v2 styling.

## Functionality that must be retained

- Whamageddon's current behaviour for existing Parties, including its settings values.
- All core `RequestRules` checks and reason strings, VIP exemption, per-user request limits, downvote limits and Blocklist rules.
- Polling, `force` (`party:force`), the device picker and device name display. All three Playback Webhooks and their URLs. Party control from the API (play, pause, next, previous).
- The TV, YouTube and display components keep receiving `Party\UpdatedEvent` on `party.{code}`.
- Search exactly as today (now on the Party country): query, pagination, in-Queue marking, every eligibility reason, requesting from results, the Spotify Search account with fallback, and E5's owner-quota protection.

## Suggested ticket slices

Foundations (1-6) come first; everything else hangs off them.

| # | Slice | Reqs | blocked_by |
|---|---|---|---|
| 1 | Track reference resolution on `MusicProvider` (Spotify and Fake) | 1 | none |
| 2 | `PlaybackState` extension, Player source tracking, `sampledAt` ordering and freshness-based poll suppression | 2, 33-37 | none |
| 3 | Pushed-state ingest: `MessageReceived` listener, queued job, channel routing and drop rules | 38 | 2 |
| 4 | Player-transport seam with the `ProviderPlayback` fallback; `control` and `party:force` through it, `seek` added | 3, 44 (API part) | 2 |
| 5 | `QueueSelector` candidate filter and add-Request hook; hold time and score adjustment as core | 4, 7 | none |
| 6 | Three-way `RequestRule` outcome, stage order, submit/annotate context, three-way `TrackEligibility` | 5, 6 | none |
| 7 | Mod contract, service-provider auto-registration, deploy sync, per-Party enablement; Whamageddon moved onto it unchanged | 17-20 | 5, 6 |
| 8 | Typed settings and the per-Party and admin Mods screens | 21, 24 | 7 |
| 9 | Instance and Party timezone settings | 25 | 7 |
| 10 | Score modifier, Queue addition and decoration extension points; move `fallback_override`/`css_classes` to mod storage | 22, 23 | 7 |
| 11 | Scheduled actions replacing tick listeners, isolated per Party | 22 | 7 |
| 12 | Flagged Requests: state, opt-in, limit, flagging Blocklist rules, approval queue, withdraw, notifications | 8-13 | 6 |
| 13 | Scheduled Requests | 14-16 | 5, 9 |
| 14 | Whamageddon port and fixes | 26, 27 | 8, 9, 10, 11 |
| 15 | AI Request Review mod | 30-32 | 8, 12 |
| 16 | Research: Eigenkarma / Trust design (research, Opus) | 28 | 7 |
| 17 | Trust implementation, per the signed-off design | 29 | 16 and user sign-off, 8, 11 |
| 18 | `sampledAt` in broadcast state; legacy `Player.vue`/`TvPlayer.vue` interpolation and decoration rendering | 39, 22 (legacy views) | 2, 10 |
| 19 | Web Player page: popup, owner-role access, token endpoint and private-channel refresh, SDK state as a whisper | 40-42 | 1, 3 |
| 20 | Web Player recovery, SDK transport, single-active takeover, navigate-away warning | 43-46 | 4, 19 |
| 21 | Encore channel, Soloist frame mapping, `get_state` on connect, Soloist command transport | 47-50 | 1, 3, 4 |
| 22 | Encore setup display and README section; logged-out, error and silence handling | 47, 51 | 21 |
| 23 | Party country setting and per-member search/browse leaky bucket | 53, 54 | 6 |
| 24 | Album page | 55, 57, 59-62 | 23 |
| 25 | Artist page | 55, 58-62 | 24 |
| 26 | Search result links and live Queue state on browse pages | 56, 60 | 24, 25 |

## Risks and open questions

Implementation-level; settle when tickets are written.

- **Mod discovery mechanism.** How `app/Mods/*/` service providers are found and registered (a core provider scanning the directory at boot, or a cached manifest built by the sync command), and its cost under Octane.
- **Moderator.** Which existing `PartyMemberRole` codes count as "moderator" for Scheduled Requests.
- **Scheduled Request entry point.** Where the schedule control lives (the request action on `TrackRow`, or editing an existing Request), and whether a Scheduled Request goes through the request limit.
- **Leaky-bucket values.** Default leak rates and burst sizes for the per-member search/browse limit, and where they are configured.
- **Party country fallback** when the owner's market is unknown.
- **Staleness window per source.** Soloist emits about every 10 s while playing, but nothing while paused.
- **Insufficient pushed state.** Candidates: Queue contents for Sent to Player (Encore's `queue_changed` vs the provider's queue), album art (Soloist `item` has no art URL; `MusicProvider::getTrack` can fill it), relinked tracks (`linkedFromId`).
- **Transport coverage.** Which actions Soloist and the Web Playback SDK support; the SDK has no queue-add, so Sent to Player may still need the Web API.
- **Encore (re)connect detection.** The first frame after silence, or the subscription seen in `MessageReceived`. Verify `get_state` against Soloist before relying on it.
- **Reverb.** Confirm client events from a secret-signed subscription and from browser whispers reach `MessageReceived` after E3's upgrade, and how this behaves with Redis scaling across Reverb servers.
- **Web Player channel layout.** Whether token refresh, state whispers and takeover share one per-Party private channel or use separate ones.
- **Trust scope** is unknown until the research is signed off; slice 17 may split and may also need slice 10 or 12.
