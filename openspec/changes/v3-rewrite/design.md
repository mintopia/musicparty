# Design

## Context

See proposal.md (Why) for the motivation. The constraints that shape the approach:

- The stack is fixed: Laravel 13, PHP 8.4 on FrankenPHP and Octane, MariaDB, Redis, Horizon, Reverb, and Inertia with Vue 3 and Tailwind v4. First-party Laravel packages are preferred, and any deviation needs an ADR.
- Five decisions are already recorded and are not restated here: ADR-0001 (separate Music Provider and Player, one Provider per Party), ADR-0002 (code-first OpenAPI with a route-coverage check, and Actions shared by Inertia and API), ADR-0003 (Soloist connects over Reverb using the Pusher protocol), ADR-0004 (first-party Mods with fixed extension points) and ADR-0005 (only instance admins issue Integration Tokens).
- Vocabulary follows `GLOSSARY.md`. Requirements live in `specs/<capability>/spec.md`, and this document describes how to build them.
- Octane keeps workers alive between requests, so no request-scoped state may live in singletons or statics.
- v2 (`../musicparty-v2`) is a behavioural reference only. No code or data is migrated.

## Goals / Non-Goals

**Goals:**

- No god classes. Each bounded context owns its models, Actions, events and policies.
- One code path per capability. An Inertia controller and an API controller call the same Action.
- Provider, Player and Mod are seams that a fake can satisfy, so the domain can be tested without Spotify or Soloist.
- Meet the scaling target by configuration only.
- Every HTTP route and every broadcast is machine-documented and checked in CI.

**Non-Goals:**

- A plugin marketplace or runtime loading of third-party code.
- Multiple Players per Party, or switching Player mid-Track.
- A redesign of the desktop guest look and feel. Desktop guest pages match the v2 screenshots. Mobile is the exception: v2's mobile layout is not fit for purpose, so mobile is redesigned mobile-first in the same visual style (bottom tab bar, mini now-playing bar, full-screen search) and refined after build.
- Horizontal scaling beyond the stated target. It is enabled by configuration but not tuned or load-tested past the target.

## Decisions

### D1. Bounded contexts under `app/Domain`

The code is split into contexts. Each one holds its own `Models`, `Actions`, `Events`, `Policies`, `Data` (DTOs) and `Contracts` as needed:

| Context | Owns |
|---|---|
| `Party` | Party, Party states, settings, Party Log, Fallback readiness |
| `Membership` | Member, Party Roles, Ban, act-as-Host sessions |
| `Queue` | Request, Vote, Play, Rating, request rules, selection strategies |
| `Music` | Music Provider contract, Track value objects, Spotify Provider |
| `Playback` | Player contract, Player kinds, Player Tokens, adapters, Feed Mode orchestration |
| `Mods` | Mod registry, extension point contracts, Decorations, first-party Mods |
| `Theming` | Instance Theme, Party Theme, token schema, contrast checks |
| `Stats` | Live Stats projections, Party Export |
| `Admin` | site settings, social provider credentials, Integration Tokens |

HTTP lives in `app/Http/Controllers/{Web,Api/V1}` and broadcast authorisation in `routes/channels.php`. Contexts talk to each other through Actions and domain events, never by reaching into another context's models to change them. The project keeps its Laravel 10-style structure (Kernel and providers) rather than moving to `bootstrap/app.php`.

*Alternatives:* a flat `app/Models` plus services, as in v2, which led to the 800-line Party and the 268-line User. We also considered separate Composer packages per context, which adds overhead with no deployment benefit.

### D2. Actions shared by Inertia and API controllers

Each use case is a single-method invokable Action class, for example `SubmitRequest`, `CastVote`, `GoLive` or `IssuePlayerToken`. An Action takes a typed DTO built from a Form Request and returns domain objects. Web controllers map the result to Inertia props, and `Api\V1` controllers map it to API Resources. Authorisation runs in Policies, which both controllers call, so the two surfaces cannot drift (ADR-0002). Actions dispatch domain events, and broadcasting and Mod listeners react to those events. Actions never broadcast directly.

*Alternative:* having the UI consume `/api/v1` as an SPA. We rejected it because Inertia props give server-side authorisation and no API round-trips for page loads, and ADR-0002 already records this choice.

### D3. Music Provider contract

`Music\Contracts\MusicProvider` covers search, getting a Track, getting playlist Tracks, appending to a playlist, checking a Track's playability in a market, and normalising Tracks into a provider-neutral `TrackData` (id, ISRC, name, artists, album, duration, explicit, artwork). Spotify search uses instance-level client credentials, cached in Redis. Host-account operations (Fallback and History Playlists, and the Polling and Browser Players) use the Host's linked account through a token store, which refreshes tokens and encrypts them at rest. Tokens never leave the server except through D6's private, Host-only channel. Tests bind a `FakeMusicProvider`.

### D4. Player contract, Compatibility and Feed Modes

`Playback\Contracts\Player` defines `kind()`, `compatibleProviders()` (Compatibility), `feedMode()` (`Ahead` or `JustInTime`), `requiresHostAccount()`, and command methods (`play`, `pause`, `skip`, `seek`, `volume`, `enqueue`). It also reports observed state as `PlaybackState`. A Party stores one Player kind, and the `PairPlayer` Action rejects incompatible pairs.

A `PlaybackCoordinator` in `Playback` reacts to observed state, whatever the Player kind:

- **Ahead** (Polling): after each Track change, lock the next Up Next and enqueue it, so that Spotify's queue holds exactly one Up Next.
- **JustInTime** (Soloist, Browser): about 15 s before the current Track ends, select, lock and enqueue. A locked Up Next is final.

The coordinator never enqueues a Track while the Queue is empty and nothing is eligible. In that case it stops playback so that Provider autoplay cannot take over. Tests bind a `FakePlayer` that records commands and lets a test script state transitions.

### D5. Player adapter flow (Reverb → validate → Horizon job)

For connected Players (Soloist and future integrations of the same shape):

1. The client connects to Reverb over the Pusher protocol and subscribes to `private-player.{playerId}`. Channel authorisation checks a Sanctum Player Token with the `player:connect` ability, scoped to that Party and not revoked.
2. The client sends its native frames unchanged, wrapped as Pusher client events.
3. A Reverb message listener checks only the envelope: an authorised channel, a known Player kind, a size limit and a rate limit. It then dispatches `HandlePlayerMessage` to a dedicated Horizon queue. No domain logic and no database writes happen in the Reverb process.
4. The job resolves the adapter for the Player kind (for example `SoloistAdapter`), which parses the frame into `PlaybackState` or a command result and hands it to the `PlaybackCoordinator`.
5. Commands go back as server events on the same private channel, serialised by the adapter into the client's native frame.

Connects and disconnects are written to the Party Log. The Polling Player runs as a self-rescheduling job (delay ≈ min(60 s, max(5 s, remaining/2))) under `WithoutOverlapping` keyed by Party. A Webhook call, authenticated with a Player Token, only dispatches that job immediately. The Browser Player authenticates as the Host session and receives its short-lived access token over a private, Host-only channel or an Inertia prop. The token never goes on a public channel. The Browser Player reports Web Playback SDK events through the API.

### D6. Realtime channels

| Channel | Type | Audience | Content |
|---|---|---|---|
| `party.{code}` | public | anyone | now playing, Up Next, Queue (Track, Score, requester nickname, Decorations), Party state |
| `presence-party.{code}.members` | presence | Members | who is online |
| `private-member.{partyId}.{userId}` | private | that Member | own Votes, own Pending Requests, notifications |
| `private-party.{code}.moderation` | private | Host and Moderators | Pending Requests, Party Log entries |
| `private-player.{playerId}` | private | that Player connection | Player messages and commands (D5) |

Public payloads are built by dedicated Broadcast Resources that exclude member ids and anything secret. A Pest architecture test forbids broadcast events from serialising models directly. Queue updates are coalesced per Party (debounced job) so that a burst of Votes produces one broadcast, which keeps 500-member Parties within budget.

### D7. Selection strategies

`Queue\Contracts\SelectionStrategy` has two implementations, chosen per Party:

- `DeterministicSelection`: highest effective Score first, then oldest.
- `WeightedRandomSelection`: a roulette over positive effective Scores, ignoring Requests whose `not_before` is in the future. The random source is injected, so tests seed it and stay deterministic.

Effective Score = the sum of Votes plus the adjustments from enabled Score Modifiers, applied at selection time and not stored. `LockUpNext` runs in a transaction with a row lock on the Party, so concurrent Polling jobs and Player messages cannot lock two Up Next Requests.

### D8. Mod extension points

Mods are classes implementing `Mods\Contracts\Mod` (key, name, settings DTO class, default settings), registered in `ModServiceProvider` (ADR-0004). A Mod opts into an extension point by implementing its interface:

- `RequestRule::evaluate(RequestCandidate): RuleVerdict` (Accept, Reject with reason, or Hold → Pending). Core rules (Blocklist, limits and so on) use the same interface and run first. Mod rules run after them in a fixed priority order.
- `ScoreModifier::adjust(Request, SelectionContext): int`
- `ScheduledAction` declares its schedule. A single scheduler command fans out to Parties where the Mod is enabled and may create system Requests through the `SubmitSystemRequest` Action.
- `Decorator::decorate(Request|Play): list<Decoration>`. A Decoration is a typed DTO with `badge`, `label`, `icon`, `accentToken` and a `variant` from an allow-list, and it never carries HTML or CSS. Decorations are included in API Resources and broadcast payloads, so the OpenAPI and AsyncAPI schemas cover them.
- Domain event listeners, registered by the Mod and called only when the Mod is enabled for the event's Party.
- UI slots: Vue components registered at build time into named slots (for example `request.after-title` or `tv.sidebar`) through a static registry in `resources/js/mods/`.

Per-Party enablement and settings are stored as validated JSON against the settings DTO. Mod auto-actions are written to the Party Log. **AI Request Review** is a `RequestRule` built on `laravel/ai` Classification, with an OpenAI driver and a Jev (TypeSafe) driver. It runs as a queued job: the Request is held as Pending, then accepted or rejected when the job finishes. If the AI times out, the result falls back to a Party-configured default verdict.

### D9. Token and theming layering

Design tokens (`--mp-color-primary`, `--mp-color-accent`, `--mp-color-background`, `--mp-color-surface`, `--mp-color-text`, `--mp-color-danger`, `--mp-font-sans` and others) are CSS custom properties declared in Tailwind v4's `@theme`, so utilities such as `bg-primary` resolve to variables. The values come in three layers, applied in this order:

1. **Instance Theme**: defaults seeded from the v2 Tabler values and edited by admins, rendered as `:root` variables in the Inertia root view.
2. **Party Theme**: overrides a subset of tokens (colour tokens, a font from the curated list, logo, background image and TV layout preset), rendered as variables scoped to the Party layout.
3. **Colour Scheme**: the user's light, dark or system preference toggles a `data-scheme` attribute. Each theme stores light and dark values for each colour token.

Token values are validated server-side (colour format, font from the allow-list) and emitted as variable declarations only, never as free CSS. WCAG AA contrast is checked for text/background and text/surface pairs when a theme is saved. A failed check shows a warning and does not block the save.

### D10. OpenAPI and AsyncAPI CI checks

- OpenAPI: generated from Form Requests and API Resources (dedoc/scramble, per ADR-0002). The generated document is committed as `docs/api/openapi.json`. A Pest test regenerates the document and fails on any diff, and fails if any `/api` route is missing from it.
- AsyncAPI: `docs/api/asyncapi.yaml` is hand-written, because no first-party generator exists. A Pest test reflects every class implementing `ShouldBroadcast` and every Player adapter message type, and fails if any event name, channel or payload schema is missing from the document. Payload schemas are checked against the Broadcast Resources' output in the test. The Mod Decoration schema is shared by both documents.

Both documents are validated with the spec linters in CI.

### D11. Scaling

The target is 25 Live Parties, 500 Members per Party and about 1,000 concurrent connections per instance. To get there:

- One Reverb process with its Redis scaling option enabled in configuration, so adding Reverb nodes needs no code change.
- Separate Horizon queues for `player`, `polling`, `broadcast`, `mods-ai` and `default`, each with its own worker count, so slow AI review cannot starve Player messages.
- Coalesced Queue broadcasts (D6), and Live Stats computed incrementally from domain events into Redis rather than recomputed per request.
- Octane-safe code, checked by an architecture test that bans mutable static state in `app/Domain`.

A load-test task checks the target with simulated Pusher clients.

## Risks / Trade-offs

- **Soloist single-track `play` autoplay behaviour is undocumented.** After a single-track `play`, Spotify might continue into autoplay or context instead of going idle. → The JustInTime feed uses `add_to_queue`. The adapter also watches `queue_changed` and `track_changed` for items with `source: autoplay` or `context`, and when it sees one it pauses or skips and logs a Party Log warning. A live-instance spike task settles the behaviour before the Soloist Player is finished.
- **Soloist cannot remove queued items.** A Vote after Up Next is locked, or a Moderator removing the Up Next Request, cannot be undone on the Player. → Lock as late as possible (about 15 s before the end), and make the UI show Up Next as locked so Votes are disabled for it. Removing a locked Up Next skips the Track when it starts. Do not add an Ahead mode for Soloist.
- **The Jev Score mapping may be lossy.** `laravel/ai` Classification's `Score` (0.0–1.0) may not map cleanly onto Jev's ordinal rubric levels and criteria. → A spike task compares the two on sample rubrics. If the mapping is lossy, implement a thin Jev HTTP client behind the same driver interface and record an ADR.
- **The v2 security issues must not reappear.** These are the public token broadcast (`spotifytoken.{id}`), the unauthenticated Soloist webhook, and the `party.{party}` channel that authorises everyone. → Provider tokens are never serialised into broadcast payloads (architecture test). Every webhook and Player route needs a Player Token (feature tests for 401 and 403). Every private and presence channel has a negative authorisation test.
- **Hand-written AsyncAPI can drift.** → The CI reflection test in D10 catches drift, and payload schemas are checked against real Broadcast Resource output.
- **Reverb client events** have to be enabled for Player channels, which opens them to abuse. → Client events are accepted only on `private-player.*`, with size and rate limits enforced in the listener. They are ignored on every other channel.
- **The Fallback rule of 20 Tracks** may block small test parties. → The 20-Track rule applies as specified. Factories and the FakeMusicProvider supply eligible playlists for tests.
- **Matching v2 visuals with Tailwind instead of Tabler** risks drift from the look and feel. → The desktop reference screenshots in `docs/design/v2-reference/`, the mobile layout brief in the theming spec, and a visual review step on each guest-page task.

## Migration Plan

There is no data migration. v3 deploys as a new instance with fresh databases. The order is:

1. Ship Foundation and the domain behind the admin gate.
2. Run v3 beside v2 at a test party.
3. Switch DNS.

Rollback means pointing DNS back to the untouched v2 instance. Both Docker Compose stacks stay deployable until v3 has run a real party.

## Open Questions

- The exact Tailwind token names, and which v2 Tabler values seed the Instance Theme. Settled while building Epic 1 and the screenshot matching in Epic 6.
- The final Horizon worker counts per queue. Tuned by the load test.
