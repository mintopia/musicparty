# Design

## Context

See proposal.md for why and ADR-0034 for the decision. The base is `feature/v3-rewrite` at bb2b006.

What exists today:

- Registry: `config/musicparty.php` `music_providers` holds only `fake`. `players` holds `polling`, `browser` and `soloist`, and all three declare Compatibility with `spotify` only. `PlayerFactory::make()` resolves `config("musicparty.players.{kind}.class")`.
- `PairingCatalogue` (`app/Domain/Party/PairingCatalogue.php`) builds `providers()` and `players()` from config. Its other methods (`provider()`, `player()`, `trackUrl()`, `playlistUrl()`) resolve a registered class for running Parties. It is used by `CreateParty`, `ChangePartyPlayer`, `PartyController::create` and `settings`, `FallbackPlaylistGate`, `RequestTrack`, `TopUpFallbackRequests`, `SearchPartyProvider`, `QueueEntryPresenter`, `PlayResource`, `BuildPartyPlaylistCsv` and the history-playlist listener and job.
- `StorePartyRequest::rules()` builds `in:` rules from both config keys. `ChangePartyPlayer` checks the kind and provider against `PairingCatalogue::players()` and `providers()`.
- `Create.vue` already filters Players by `compatibleProviders` client-side.
- Site settings: the `settings` table and `Setting::fetch($code)`, cached in the default cache store (Redis in production, `array` in tests) under `settings.{code}`, cleared by `SettingObserver::saved`. There is no `deleted` hook.
- Provider credentials: `SocialProvider` with `ProviderSetting` rows (encrypted by `SettingValue`). `SocialProvider::isConfigured()` checks that every required setting is non-blank. `getSetting()` memoises on the model instance only. Admin writes go through `UpdateSocialProvider`, which audits.
- Spotify credential readers: `SpotifyApi::configured()` reads `config("services.{app}.{key}")`; `HostAccountTokens::requestRefresh()` reads `config("services.{app}.client_id|client_secret")`; `SearchCredentials::searchProviderEnabled()` mixes the DB `enabled` flag with `SpotifyApi` config values.
- `providers:seed` (`SeedSocialProviders`) fills empty provider settings from `config/services.php` and never overwrites.

Constraints: ADR-0012 (only Admin Actions write `Setting`; Identity owns `SocialProvider` and `ProviderSetting`; other contexts read through a reader), ADR-0024 (in-memory SQLite tests), ADR-0021 and ADR-0028 (backoff keyed by `sha256(clientId)`), API parity and OpenAPI coverage, the v2 look for admin pages.

## Goals / Non-Goals

**Goals:**
- Spotify selectable on Create Party once an admin has enabled it and saved its credentials.
- Admin on/off for each Music Provider and Player, applied without restarting Octane or Horizon.
- One source for Spotify client credentials: provider settings.

**Non-Goals:**
- Adding new Music Providers or Players, or a Fake Player in the registry.
- Moving `SPOTIFY_MARKET` out of config.
- A per-Party override or a per-user allowlist for providers.
- Changing how login or linking reads credentials (they already read the database).
- Cross-request caching of decrypted secrets.

## Decisions

### D1. Registry entries gain `requires`; Spotify is registered

`music_providers` entries become `['label' => ..., 'class' => ..., 'requires' => list<string>]`. `spotify` is added: label `Spotify`, class `SpotifyMusicProvider`, `requires` `['spotify']`. `fake` has `requires` `[]`. Players keep their shape. The registry stays in config because only a deploy can add a class. Config is read once per worker, which is fine for data that only changes with a deploy.

Alternative: a `requiredCredentials()` method on the `MusicProvider` contract. Rejected because the Fake and test providers would all need it, and the list is wiring rather than provider behaviour.

### D2. Enabled flags as `settings` rows, behind an Admin reader

Codes: `music_provider.<id>.enabled` and `player.<kind>.enabled`, type boolean, not encrypted. New Admin classes:

- `App\Domain\Admin\PairingToggles` (read-only): `providerEnabled(string $id): bool` and `playerEnabled(string $kind): bool`, both via `Setting::fetch($code, false)`.
- `App\Domain\Admin\Actions\SetMusicProviderEnabled` and `SetPlayerEnabled`: upsert the row and audit with `music_provider.enabled|disabled` or `player.enabled|disabled` and `['music_provider' => $id]` or `['player' => $kind]`. They refuse an id that is not in the registry.

`SettingObserver` gains `deleted` so a removed row clears its cache entry. The cached value is the `getValue()` object, so a cached `false` is a hit. The rows are not in `SiteSettings::FIELDS`, so the site settings form does not show them.

Why `settings` and not new columns or a table: decision 2 asks to reuse the existing store, and `Setting::fetch` already has the shared-cache and invalidation behaviour that Octane needs.

Data migration: inserts the five rows enabled (`spotify`, `fake`, `polling`, `browser`, `soloist`), skipping codes that exist. Its `down` deletes those codes. In tests, `RefreshDatabase` runs it, so every test starts with all five enabled.

### D3. Availability in `PairingCatalogue`

`PairingCatalogue` keeps resolving any registered class (`provider()`, `player()`, `trackUrl()`, `playlistUrl()`), so running Parties are unaffected. It changes as follows:

- `providers()` returns only available Music Providers: registered, `PairingToggles::providerEnabled`, every `requires` code `isConfigured()` per `ProviderCredentials` (D5), and for `fake` only when `app()->environment('local', 'testing')`.
- `players()` returns only enabled Player kinds, each with `compatibleProviders` intersected with the available provider ids. A kind with no available compatible provider is left out.
- New `isProviderAvailable(string $id): bool` and `isPlayerAvailable(string $kind, string $providerId): bool`.
- New `registeredProviders()` and `registeredPlayers()` for the admin page, each with `enabled`, `configured`, `requires`, `compatibleProviders` and, for providers, `localOnly`. The admin list leaves out `fake` outside `local` and `testing`.

`PairingCatalogue` is `readonly` and container-resolved per use. It holds no state between requests, so it is Octane-safe.

### D4. Validation and the "keep current pairing" rule

- `StorePartyRequest::rules()` uses `Rule::in(array_column($catalogue->providers(), 'id'))` for `music_provider`. `player_kind` uses `Rule::in` over registered kinds, plus an `after()` check that `isPlayerAvailable(kind, provider)` holds, with the message "This player is not available for the chosen music provider."
- `CreateParty` asserts availability too (a guard for API and console callers), throwing `ValidationException`.
- `ChangePartyPlayer` allows the request when the new `(player_kind, music_provider)` equals the Party's current pair (so a Host can save settings, or change only the device, on a disabled pairing). Otherwise both must be available. The existing "Unknown player kind" message stays for unregistered kinds, and a disabled kind gets "This player has been disabled by an admin."
- The Party settings page's Player picker uses `players()`, plus the Party's current kind when that kind is no longer available, labelled "(disabled)".

Existing Parties: no Party is paused, ended or migrated when something is disabled. Reasoning: the main user is a LAN party, and an admin toggling a setting must not stop music mid-event. If credentials are removed, Spotify calls already fail as `ProviderUnavailableException`, which the existing handling surfaces to the Host. That is unchanged.

### D5. Spotify credentials from provider settings

New Identity reader `App\Domain\Identity\ProviderCredentials`, bound as `scoped` in a service provider:

- `get(string $providerCode, string $settingCode): ?string` returns the decrypted value, or null when it is blank or missing.
- `isConfigured(string $providerCode): bool` delegates to `SocialProvider::isConfigured()`.
- It memoises per instance. A scoped binding is flushed by Octane at the end of each request and by the queue worker before each job, so a saved credential is seen by the next request or job. No secret goes to the cache store.

Changes:

- `SpotifyApi::clientId()` and `clientSecret()` read `ProviderCredentials` for `spotify` or `spotifysearch`. `market()` keeps reading `services.spotify.market`.
- `HostAccountTokens::requestRefresh()` uses `SpotifyApi::clientId($application)` and `clientSecret($application)` instead of `config()`.
- `SearchCredentials::searchProviderEnabled()` uses `SocialProvider` `enabled` plus `ProviderCredentials::isConfigured('spotifysearch')`.
- `backoffKey()` is unchanged. It hashes whatever client id is current, so a changed client id gets its own backoff, which matches ADR-0021 and ADR-0028.
- `providers:seed` also seeds `spotifysearch` from `SPOTIFYSEARCH_CLIENT_*` (the catalogue already has it) and stays the only reader of the client keys. `config/services.php` gains a comment saying these keys are a seed for `providers:seed`.
- Architecture test: no file under `app/` other than `SeedSocialProviders` contains `services.spotify.client_`, `services.spotifysearch.client_` or `services.{$application}`.

### D6. Admin UI and API

- `Admin/Settings.vue` gets two cards after Social Providers, built with `Card`, the table markup and `StatusPill` used there, and `ButtonLink`/form buttons in the same variants:
  - **Music Providers**: Name and id, Enabled pill, Configured pill (green "Yes", red "No" with a link to `/admin/providers/{code}/edit` for each missing `requires` code), Parties (non-ended count), Enable/Disable button.
  - **Players**: Name and kind, Enabled pill, Compatible with (provider labels), Parties (non-ended count), Enable/Disable button.
  - Disabling something used by Parties asks for confirmation with `window.confirm`, as `Admin/Theme.vue` does for its reset: "N parties use this. They keep working, but no new party can choose it."
- Web routes in the `can:admin` group: `PUT admin/music-providers/{provider}` (`admin.music-providers.update`) and `PUT admin/players/{player}` (`admin.players.update`), body `{enabled: bool}`, through Form Requests that validate the id against the registry. Flash `success` with "Spotify enabled." or similar.
- API: `GET /api/v1/admin/music-providers`, `PUT /api/v1/admin/music-providers/{provider}`, `GET /api/v1/admin/players`, `PUT /api/v1/admin/players/{player}` under `admin.principal`, with `AdminMusicProviderResource` and `AdminPlayerResource`. Added to OpenAPI and to `AdminApiParityTest`.
- `AdminSettingsController::show` passes `musicProviders` and `players` from `PairingCatalogue::registeredProviders()` and `registeredPlayers()`, with Party counts from one grouped query each (no N+1).

### D7. Test seams

- `tests/Pest.php` gains `configureSpotifyCredentials(string $app = 'spotify', string $id = 'client-id', string $secret = 'client-secret'): SocialProvider`, which writes provider settings through `EnsureSocialProvider` and `ProviderSetting`. It also gains `setPairingToggle(string $code, bool $enabled)`, which writes through the Admin Action. Tests that set `services.spotify*` credentials switch to the helper. Tests that register `musicparty.music_providers.spotify` keep doing so where they substitute a class.
- All Spotify HTTP uses `Http::fake` with `Http::preventStrayRequests()`. Assertions check the Basic auth header and the `client_id` the request was sent with.
- "Without a restart": a test resolves `PairingCatalogue` once, flips the toggle through the Admin Action, and resolves again in the same process (the same as an Octane worker reusing the app). A second test calls `app()->forgetScopedInstances()` between two `ProviderCredentials` reads around a credential update, to prove the scoped binding sees the new secret.
- Environment: `app()->detectEnvironment(fn () => 'production')` inside a test, restored afterwards, for the fake-hidden scenarios.
- UI: a Vitest test for the two new tables (pills, the confirm prompt, the missing-credential link), and a feature test of the Inertia props. Screenshots at 1280px and 390px, light and dark, compared with the existing Social Providers card and v2 `admin/socialproviders` reference.

## Risks / Trade-offs

- [An install that only set `SPOTIFY_CLIENT_*` in env loses Spotify after upgrading] → The upgrade notes and the README say to run `providers:seed` once. The admin Music Providers table shows "Not configured" with a link. The data migration does not copy env values, because migrations should not read secrets from env.
- [One credentials query per request or job that touches Spotify] → It is a small indexed query, memoised for the request. The poll job already does several queries per tick.
- [A toggle cached in Redis can be stale if the row is written outside Eloquent] → Only the Admin Actions write these rows, and the observer clears the cache on save and delete.
- [Keeping a disabled pairing on existing Parties means "disabled" is not absolute] → This is intended (D4). The admin table shows the Party count so the admin knows.
- [`testing` is allowed for the Fake provider although the decision says "local"] → Tests need it, and `testing` is never a deployed environment. See Open Questions.

## Migration Plan

1. Deploy the code and run migrations. The data migration enables the five registry entries.
2. Admins whose Spotify credentials were env-only run `providers:seed` (or enter them under `/admin/providers`).
3. Spotify appears on Create Party when its credentials are configured.
4. Rollback: the migration's `down` removes the toggle rows. The old code ignores them and reads env again.

## Task group ordering (drives ticket `blocked_by` edges)

| Group | Blocked by | Why |
|---|---|---|
| 1. Spotify credentials from provider settings | none | Independent of toggles; unblocks the "configured" check |
| 2. Toggles and availability | 1 (for `ProviderCredentials::isConfigured`) | Availability needs the credentials reader |
| 3. Admin UI and API | 2 | Shows and writes the toggles |
| 4. Docs and full-suite integration | 1, 2, 3 | Final checks |

## Open Questions

1. **Is `testing` acceptable alongside `local` for the Fake Music Provider?** The decision says local only. The test suite runs as `testing` and depends on the Fake provider. This design allows both and never `production`. If the owner wants strictly `local`, the alternative is for `phpunit.xml` to run as `local`, which also changes other environment-dependent behaviour.
2. **Does "required credentials configured" include the `spotify` social provider's own `enabled` flag?** This design uses `isConfigured()` only, so an admin can turn off Spotify login while keeping Spotify for music. If the owner wants the social provider's flag to also gate the Music Provider, it is a one-line change in D3.
