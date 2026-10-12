## ADDED Requirements

### Requirement: Music Provider and Player toggles
Admins SHALL be able to enable and disable each registered Music Provider and Player kind from the admin Settings page and through the Admin API, with the same authorisation. Each change SHALL be audited and SHALL NOT change any existing Party. The page SHALL match the existing v2-styled admin look.

#### Scenario: Enable a Music Provider
- **WHEN** an admin enables Spotify on the admin Settings page
- **THEN** Spotify shows as enabled, an audit record `music_provider.enabled` names the admin and Spotify, and Spotify is offered on Create Party if its credentials are configured

#### Scenario: Missing credentials shown
- **WHEN** Spotify is enabled but its `spotify` client secret is blank
- **THEN** the Music Providers table shows Spotify as not configured, links to the Spotify provider settings, and Spotify is not offered on Create Party

#### Scenario: Disable a Player in use
- **WHEN** an admin disables the Polling Player while two Parties use it
- **THEN** the table showed that two Parties use it before the change, an audit record `player.disabled` is written, and both Parties keep the Polling Player

#### Scenario: Fake hidden from the admin list outside local
- **WHEN** an admin opens the Settings page in production
- **THEN** the Fake Music Provider is not listed

#### Scenario: Admin API parity
- **WHEN** an admin token calls the Admin API to disable the Browser Player
- **THEN** the Browser Player is disabled exactly as from the web page, and the change is audited

#### Scenario: Non-admin refused
- **WHEN** a user without the admin role tries to enable or disable a Music Provider or Player through the web route or the API
- **THEN** the request is refused and nothing changes

### Requirement: Music Provider and Player status listing
The admin Settings page SHALL list each registered Music Provider with its enabled state, whether its required credentials are configured, and its count of non-ended Parties. It SHALL list each registered Player kind with its enabled state, its compatible Music Providers, and its count of non-ended Parties.

#### Scenario: Listing shows status
- **WHEN** an admin opens the Settings page with Spotify enabled and configured, the Browser Player disabled, and one Live Spotify Party on the Polling Player
- **THEN** Spotify shows enabled, configured and one Party; the Browser Player shows disabled and compatible with Spotify; the Polling Player shows one Party
