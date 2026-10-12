# Tasks

Each checkbox is about one PR, done in its own worktree off `feature/v3-rewrite`. Each must pass PHPStan level 8, Pint and Rector. Tests run on in-memory SQLite with no other service (ADR-0024), and every Spotify call in a test goes through `Http::fake` with `Http::preventStrayRequests()`. While working, run only the affected tests; run the full suite before merging. Flaky tests block the merge.

Any change to an `/api` route or API Resource updates the OpenAPI document in the same PR. Every new admin UI action gets its `/api/v1/admin` equivalent with the same authorisation.

Group ordering and `blocked_by` edges are in design.md under "Task group ordering". The two open questions in design.md are confirmed with the owner before task 2.2 starts.

## 1. Spotify credentials from provider settings

- [ ] 1.1 Add the `ProviderCredentials` reader and test helpers (design D5, D7).
  - Add `App\Domain\Identity\ProviderCredentials` (`get()`, `isConfigured()`), bound `scoped` in a service provider.
  - Add `configureSpotifyCredentials()` to `tests/Pest.php`.
  - Verify with unit tests: a stored encrypted secret comes back decrypted; a blank or missing setting returns null; `isConfigured()` is false when any required setting is blank; after a credential update and `app()->forgetScopedInstances()`, the new value is returned; within one scope, a second read does not query again (`countQueries`).
- [ ] 1.2 Point `SpotifyApi`, `HostAccountTokens` and `SearchCredentials` at provider settings (design D5).
  - `SpotifyApi::clientId()` and `clientSecret()` read `ProviderCredentials`. `market()` is unchanged.
  - `HostAccountTokens::requestRefresh()` uses `SpotifyApi` for the client id and secret.
  - `SearchCredentials::searchProviderEnabled()` uses `ProviderCredentials::isConfigured('spotifysearch')`.
  - Move the 21 test files that set `services.spotify*` client credentials to `configureSpotifyCredentials()`.
  - Verify with `Http::fake` tests:
    - the client-credentials token request for search sends Basic auth built from the stored `spotify` id and secret while the env and config values are empty;
    - a control token refresh sends the stored `spotify` credentials;
    - a Search account refresh sends the stored `spotifysearch` credentials;
    - a Track lookup and a Polling Player play command that need a refresh use the stored credentials;
    - with `services.spotify.client_id` and `client_secret` set but provider settings blank, the calls throw `ProviderUnavailableException` and no HTTP request is sent;
    - after an admin updates the secret through `UpdateSocialProvider` and the scope is flushed, the next request carries the new secret;
    - the backoff key changes when the client id changes (ADR-0021 and ADR-0028 still hold).
- [ ] 1.3 Make env a seed only (design D5).
  - `providers:seed` also seeds `spotifysearch` from `SPOTIFYSEARCH_CLIENT_ID` and `SPOTIFYSEARCH_CLIENT_SECRET`.
  - Add a comment in `config/services.php` saying the client keys are read only by `providers:seed`.
  - Add the architecture test from design D5.
  - Verify with: a `SeedSocialProvidersTest` case for `spotifysearch` that fills empty values and never overwrites; the architecture test passing on the tree and failing on a fixture that reads `services.spotify.client_id`.

## 2. Toggles and availability

- [ ] 2.1 Add the toggle store (design D2).
  - Add `PairingToggles`, `SetMusicProviderEnabled` and `SetPlayerEnabled`.
  - Add `SettingObserver::deleted`.
  - Add the data migration enabling `spotify`, `fake`, `polling`, `browser` and `soloist`, with a `down`.
  - Add `setPairingToggle()` to `tests/Pest.php`.
  - Verify with tests:
    - a missing row reads as disabled;
    - toggling writes the row and an audit record (`music_provider.enabled|disabled`, `player.enabled|disabled`) naming the admin;
    - an unregistered id is refused;
    - `PairingToggles` returns the new value after the Action runs in the same process (cache cleared, no restart);
    - deleting a row clears its cache entry;
    - the migration's `up` and `down` run on SQLite and are idempotent with existing rows.
- [ ] 2.2 Register Spotify and filter `PairingCatalogue` (design D1, D3).
  - Add `requires` to the registry and register `spotify`.
  - `providers()` and `players()` return only available entries. Add `isProviderAvailable()`, `isPlayerAvailable()`, `registeredProviders()` and `registeredPlayers()`.
  - Gate `fake` on `local` and `testing`.
  - Verify with tests:
    - Spotify is listed when it is enabled and `spotify` is configured;
    - Spotify is not listed when it is disabled;
    - Spotify is not listed when it is enabled but the client secret is blank;
    - `fake` is not listed under a `production` environment even when enabled, and is listed under `local`;
    - a disabled Player is left out, and a Player with no available compatible provider is left out;
    - flipping a toggle between two `providers()` calls on the same app instance changes the result;
    - `provider('spotify')` and `trackUrl()` still resolve while Spotify is disabled.
- [ ] 2.3 Enforce availability on create and change (design D4).
  - `StorePartyRequest` uses `Rule::in` over available providers, plus the `after()` check for Player availability.
  - Add the `CreateParty` guard.
  - Add the `ChangePartyPlayer` "keep current pairing" rule and the disabled-kind message.
  - On the Party settings Player picker, show the current kind labelled "(disabled)" when it is unavailable.
  - Verify with feature tests (web and API):
    - creating a Party with a disabled provider fails on `music_provider` and creates no Party;
    - creating one with an enabled provider whose credentials are missing fails the same way;
    - a disabled Player fails on `player_kind`;
    - `fake` is rejected under `production`;
    - Create Party props list Spotify and its three Players when all are available (and the Spotify Polling Player still needs a control link);
    - a Host on a now-disabled Polling Player can save the same Player and change the device;
    - a Host cannot switch to a disabled Soloist Player;
    - a Live Party on a disabled provider still searches (`Http::fake`) and its playback tick still runs.

## 3. Admin UI and API

- [ ] 3.1 Add the admin Music Providers and Players tables (design D6). Build two cards in `Admin/Settings.vue` that match the Social Providers card (same `Card`, table, `StatusPill` and button variants; v2 reference `docs/design/v2-reference/` admin settings and providers screenshots).
  - Add the web routes `admin.music-providers.update` and `admin.players.update`, with Form Requests and flash `success`.
  - `AdminSettingsController::show` passes the lists with Party counts from grouped queries.
  - Verify with:
    - feature tests for the Inertia props, including Configured false with the provider edit link, non-ended Party counts, and `fake` absent under `production`;
    - feature tests that enable and disable each one with an audit record, and that a non-admin gets 403;
    - a query-count test showing the page does not grow queries with the Party count;
    - a Vitest test for the pills, the button label per state, the `window.confirm` prompt when Parties use it, and the missing-credential link;
    - screenshots at 1280px and 390px, light and dark, beside the Social Providers card.
- [ ] 3.2 Add the Admin API equivalents (design D6): `GET` and `PUT` `/api/v1/admin/music-providers` and `/api/v1/admin/players`, with `AdminMusicProviderResource` and `AdminPlayerResource`, in the OpenAPI document.
  - Verify with feature tests: an admin token lists and toggles each one with the same audit as the web route; a non-admin token gets 403; an unknown id gets 404; `AdminApiParityTest` covers both; the OpenAPI route coverage check passes.

## 4. Docs and integration

- [x] 4.1 Update the docs.
  - README "Login providers and Spotify": Spotify and Spotify Search credentials are edited under `/admin/providers`, env values only seed through `providers:seed`, and Music Providers and Players are enabled on the admin Settings page.
  - Add an upgrade note: run `providers:seed` once if Spotify credentials were env-only.
  - Add GLOSSARY entries for "Available" (Music Provider or Player) and "Registry".
- [ ] 4.2 Integration. Run the full suite, PHPStan, Pint, Rector and the OpenAPI check.
  - Manually on a local stack: save Spotify credentials in the admin UI with no `SPOTIFY_*` env, enable Spotify, and see it on Create Party without restarting Octane. Disable the Browser Player and see it disappear from Create Party on the next request.
  - Run `/no-comments`.
