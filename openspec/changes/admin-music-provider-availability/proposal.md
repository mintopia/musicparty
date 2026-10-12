# Proposal

## Why

Spotify does not appear in the Create Party Music Provider list. `SpotifyMusicProvider` and the three Spotify Players exist, but `config/musicparty.php` registers only the Fake Music Provider, and `PairingCatalogue` and `StorePartyRequest` read only that file. Changing it needs a deploy and an Octane restart.

Spotify credentials are split too. Login and linking read the admin-edited `spotify` and `spotifysearch` provider settings, while `SpotifyApi`, `HostAccountTokens` and `SearchCredentials` read `config('services.spotify*')`, which only the environment fills. An install configured through `/admin/providers` alone cannot search, look up Tracks, refresh tokens or play. v2 read these credentials from the database everywhere.

ADR-0034 records the decision this change implements.

## What Changes

- Register `spotify` in the Music Provider registry. The registry (`config/musicparty.php`) lists what exists and, for each Music Provider, the social provider codes whose credentials it requires. It no longer decides what is offered.
- Add an enabled flag for every Music Provider and every Player, stored as `settings` rows (`music_provider.<id>.enabled`, `player.<kind>.enabled`) and read through the cached `Setting::fetch`. A data migration enables `spotify`, `fake`, `polling`, `browser` and `soloist`. A missing row means disabled.
- `PairingCatalogue` reports only available Music Providers (enabled, and every required social provider configured) and available Players (enabled, and compatible with the chosen Music Provider). `StorePartyRequest`, `CreateParty` and `ChangePartyPlayer` accept only those. A Party keeps its current pairing even if it is later disabled.
- The Fake Music Provider is offered only in the `local` and `testing` environments, whatever its flag says.
- Toggles and credential changes take effect on the next web request or queued job in every Octane and Horizon worker, with no restart.
- **BREAKING (operations)** `SpotifyApi`, `HostAccountTokens` and `SearchCredentials` read Spotify and Spotify Search client credentials only from provider settings. The `SPOTIFY_CLIENT_*` and `SPOTIFYSEARCH_CLIENT_*` environment variables are read only by `providers:seed`. An install that set them in the environment but never ran `providers:seed` must run it once.
- The admin Settings page gains Music Providers and Players tables in the existing v2-styled Social Providers card style, with Enable and Disable actions, a "Configured" status and a count of Parties using each one. Each change is audited and has an Admin API equivalent.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `music-provider`: Music Provider availability (enabled and configured), the Fake Music Provider limited to local and testing, and Spotify credentials read from provider settings.
- `players`: Player availability (enabled and compatible), with existing Parties keeping their Player.
- `admin`: admins enable and disable Music Providers and Players, with audit and API parity.

## Impact

- Code: `config/musicparty.php`, `config/services.php` (comment only), `app/Domain/Party/PairingCatalogue.php`, `app/Http/Requests/StorePartyRequest.php`, `app/Domain/Party/Actions/CreateParty.php`, `app/Domain/Playback/Actions/ChangePartyPlayer.php`, `app/Domain/Music/Providers/Spotify/{SpotifyApi,HostAccountTokens,SearchCredentials}.php`, `app/Console/Commands/SeedSocialProviders.php`, `app/Observers/SettingObserver.php`, new Admin Actions and a new Identity credentials reader.
- UI: `resources/js/pages/Admin/Settings.vue`; `resources/js/pages/Party/Create.vue` and the Party settings Player picker receive filtered lists (no layout change).
- Routes: new `admin.music-providers.update` and `admin.players.update` web routes and `/api/v1/admin` equivalents, added to the OpenAPI document.
- Data: one data migration adding `settings` rows. No schema change.
- Tests: about 21 test files set `config('services.spotify*')` and several set `musicparty.music_providers.spotify`; they move to a shared helper that writes provider settings and toggles. All Spotify HTTP stays behind `Http::fake`.
- Docs: README "Login providers and Spotify" and the upgrade notes, GLOSSARY entry for "Available".
- Relation to `v2-parity-restore`: this change adds requirements and does not modify the requirements that change rewrites (Catalogue search, Spotify Provider, Social provider credentials, Party creation, One Player per Party), so both can archive in either order.
