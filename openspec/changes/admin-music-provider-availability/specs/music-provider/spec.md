## ADDED Requirements

### Requirement: Music Provider availability
A Music Provider SHALL be available only when it is registered in code, an admin has enabled it, and every credential it requires is configured; Spotify requires the `spotify` social provider's client id and secret. Only available Music Providers SHALL be offered or accepted for a new pairing. Availability SHALL be read per request, without a restart. A Party already using a Music Provider that becomes unavailable SHALL keep it.

#### Scenario: Enabled and configured provider offered
- **WHEN** an admin has enabled Spotify and saved the `spotify` client id and secret
- **THEN** the Create Party form offers Spotify

#### Scenario: Disabled provider hidden and rejected
- **WHEN** an admin has disabled Spotify and a user submits Create Party with `music_provider` set to `spotify`
- **THEN** the Create Party form does not offer Spotify, the submission is rejected with a validation error on `music_provider`, and no Party is created

#### Scenario: Enabled provider with missing credentials hidden
- **WHEN** Spotify is enabled but the `spotify` client secret is blank
- **THEN** the Create Party form does not offer Spotify and a submission choosing it is rejected

#### Scenario: Toggle applies without a restart
- **WHEN** an admin enables Spotify while Octane and Horizon workers are running
- **THEN** the next Create Party request handled by any already-running worker offers Spotify, with no worker restarted or reloaded

#### Scenario: Existing Party keeps a disabled provider
- **WHEN** an admin disables Spotify while a Spotify Party is Live
- **THEN** that Party keeps searching, requesting and playing through Spotify, and its Host can still save the Party's current Player

### Requirement: Fake Music Provider limited to development
The Fake Music Provider SHALL be available only when the application environment is `local` or `testing`. In any other environment, including production, it SHALL NOT be offered or accepted, whatever its enabled flag says.

#### Scenario: Fake hidden in production
- **WHEN** the application environment is `production` and the Fake Music Provider's flag is enabled
- **THEN** the Create Party form does not offer it and a submission choosing `fake` is rejected

#### Scenario: Fake offered locally
- **WHEN** the application environment is `local` and the Fake Music Provider is enabled
- **THEN** the Create Party form offers it

### Requirement: Spotify credentials from provider settings
Every Spotify call (search, Track lookup, playlists, playback, token refresh) SHALL use client credentials from the `spotify` social provider settings, or `spotifysearch` for the search application. Client credentials SHALL NOT be read from environment variables or config at runtime; env values MAY only seed empty settings through `providers:seed`. A saved credential SHALL be used by the next request or job without a restart.

#### Scenario: Search uses stored credentials
- **WHEN** the `spotify` provider settings hold a client id and secret, the environment holds none, and a Member searches a Spotify Party whose Host has no linked account
- **THEN** the client-credentials token request to Spotify uses the stored client id and secret, and results are returned

#### Scenario: Token refresh uses stored credentials
- **WHEN** a Host's Spotify control access token has expired and is refreshed
- **THEN** the refresh request authenticates with the `spotify` provider settings' client id and secret

#### Scenario: Search account refresh uses its own application
- **WHEN** a Host's Spotify Search access token is refreshed
- **THEN** the refresh request authenticates with the `spotifysearch` provider settings' client id and secret

#### Scenario: Track lookup and playback use stored credentials
- **WHEN** a Track is looked up, or the Polling Player sends a playback command, for a Spotify Party
- **THEN** any client token or refresh involved uses the stored `spotify` credentials and never an environment value

#### Scenario: Credential change without a restart
- **WHEN** an admin replaces the `spotify` client secret while workers are running
- **THEN** the next search request and the next queued playback job use the new secret

#### Scenario: Environment values ignored at runtime
- **WHEN** `SPOTIFY_CLIENT_ID` and `SPOTIFY_CLIENT_SECRET` are set in the environment but the `spotify` provider settings are blank
- **THEN** Spotify is unavailable for new Parties and Spotify calls fail as unavailable until an admin saves credentials or `providers:seed` copies them
