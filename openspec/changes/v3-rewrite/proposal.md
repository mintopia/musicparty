# Proposal

## Why

Music Party v2 fuses the music catalogue and playback control into one 800-line Party model, relies on Blade and Tabler with Vue islands, has no API spec, and ships known security holes: the Host's Spotify access token is broadcast on a public channel, the Soloist webhook is unauthenticated, and the `party.{party}` channel authorises everyone. v3 is a fresh build on `feature/v3-rewrite` (now on Laravel 13). It separates Music Provider from Player, makes the Queue the single source of truth, and adds first-party Mods, per-Party theming and a full documented API, so that planned features (Tidal, trust scores, Whamageddon, AI review) slot in without another rewrite.

## What Changes

- **BREAKING** Fresh v3 build with no v2 data migration. Existing parties, users and tokens are not carried over.
- **BREAKING** UI rebuilt with Inertia, Vue 3 and Tailwind v4. Blade views, Tabler/Bootstrap and Livewire are removed, along with the v2 leftovers (nonexistent controllers and observers, seat_* theme fields). Guest pages keep v2's look and feel, matched against reference screenshots in `docs/design/`.
- Party lifecycle with **Live**, **Paused** and **Ended** states. Going Live requires a Fallback Playlist with at least 20 eligible Tracks, and the Provider's autoplay never takes over silently.
- Membership by 4-letter party code. Party roles are Host, Moderator, VIP and Guest, and a Ban is a status. Instance admins get an "act as Host" mode recorded in the Party Log.
- Request lifecycle Pending → Queued → Up Next → Playing → Played (exits: Rejected, Removed), keeping every v2 request rule. Optional Pending approval per Party. Votes, Score, Ratings, and Fallback and History Playlists.
- Selection strategies per Party: deterministic or weighted random.
- **Music Provider** abstraction (ADR-0001) with Spotify as the only real Provider. Search uses instance-level client credentials, and only the Host links a Provider account.
- **Player** abstraction with Compatibility and Feed Modes: Polling Player (plus inbound Webhook), Soloist Player over Reverb using the Pusher protocol (ADR-0003), and Browser Player. Player Tokens are issued per Party and revocable.
- **BREAKING** Realtime channels redesigned: a public `party.{code}` channel with no secrets, a Member presence channel, private per-member, Moderator and per-Player channels, all documented in AsyncAPI.
- **BREAKING** Versioned `/api/v1` covering every UI capability, documented by code-first OpenAPI with a CI route-coverage check (ADR-0002). Auth is by Sanctum session, Player Tokens, or admin-issued Integration Tokens (ADR-0005).
- Theming in three layers: Instance Theme, Party Theme and Colour Scheme, as design tokens with no raw CSS. The admin-entered raw CSS from v2 is removed.
- First-party Mod system with fixed extension points (ADR-0004) and Decorations. The first Mod is **AI Request Review**.
- Live Stats and a versioned Party Export.
- Admin area: users, social provider credentials, site settings, Instance Theme, Integration Tokens, Mods catalogue, and Horizon, Pulse and Telescope behind the admin gate.
- Security fixes for the three v2 issues listed under Why.
- **Removed**: YouTube playback, the v2 Mods framework, built-in trust scores, `css_classes`, and the librespot and "simple" webhooks (replaced by the Webhook).

### Out of scope

- Tidal (or any second Music Provider) and mixed-Provider queues
- Trust score / EigenKarma Mod
- Whamageddon Mod
- AI track suggestions and AI-generated Fallback Playlists
- v2 data migration
- YouTube playback

The Mod extension points must still allow the three deferred Mods to be added later without core changes.

## Capabilities

### New Capabilities

- `party-lifecycle`: Party creation and party code, Live/Paused/Ended states and reopening, Fallback Playlist readiness and exhaustion handling, Party settings, Party Log.
- `membership`: joining by code, login via Socialite, Party Roles (Host, Moderator, VIP, Guest), Bans, instance roles and act-as-Host.
- `requests-queue`: Request lifecycle and rules (limits, length, explicit filter, Blocklist, no-repeat, dedupe), Pending approval, Votes, Score, Ratings, Fallback top-up, History Playlist.
- `queue-selection`: choosing and locking Up Next with the deterministic and weighted-random strategies, and applying Score Modifiers at selection time.
- `music-provider`: the Music Provider contract (search, Track metadata, playlists, playability) and the Spotify implementation with client-credentials search and Host account linking.
- `players`: the Player contract, Compatibility, Feed Modes, Player Tokens, and the Polling (with Webhook), Soloist and Browser Players.
- `realtime`: broadcast channels, their authorisation and payloads, and the AsyncAPI document with its CI check.
- `api`: `/api/v1`, authentication (session, Player Token, Integration Token), and the OpenAPI document with drift and route-coverage checks.
- `theming`: Instance Theme, Party Theme and Colour Scheme as design tokens, uploads, TV layout presets, and contrast warnings.
- `mods`: Mod registration, per-Party enablement and typed settings, the extension points, Decorations and UI slots, and the AI Request Review Mod.
- `stats`: Live Stats and the versioned Party Export.
- `admin`: instance administration of users, provider credentials, site settings, Instance Theme, Integration Tokens, the Mods catalogue, and the operational dashboards.

### Modified Capabilities

None. `openspec/specs/` is empty, so every capability is new.

## Impact

- **Code**: the whole application is rebuilt on `feature/v3-rewrite`. `resources/views`, the Sass/Tabler assets and Livewire are removed, and `app/` is reorganised by bounded context (see design.md).
- **Dependencies**: adds `inertiajs/inertia-laravel` and `@inertiajs/vue3`, Tailwind v4, an OpenAPI generator (e.g. `dedoc/scramble`, per ADR-0002), and `laravel/ai`. Removes `@tabler/core`, Bootstrap/Sass and Livewire. Any new non-first-party package needs an ADR.
- **APIs**: new `/api/v1` and new broadcast channels. v2 API and webhook URLs are not preserved.
- **Related systems**: the Soloist proxy (`../musicparty-soloist`) gains Pusher-protocol support. The external Wrapped-style site consumes the Party Export.
- **Operations**: Reverb, Horizon and Redis become mandatory. The target is 25 Live Parties, 500 Members per Party and about 1,000 concurrent realtime connections per instance.
