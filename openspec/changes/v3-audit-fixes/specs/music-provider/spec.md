## MODIFIED Requirements

### Requirement: Encrypted token storage and refresh
The system SHALL store Music Provider access and refresh tokens encrypted at rest, SHALL refresh access tokens before they expire or on an authorisation failure, and SHALL NOT expose a token in any API response, broadcast, log or page. An account SHALL be marked as needing re-linking only when the Music Provider explicitly rejects the grant (for Spotify, `invalid_grant`). Any other refresh failure SHALL be treated as the Provider being unavailable.

#### Scenario: Token refreshed transparently
- **WHEN** a request needs a linked account and its access token has expired
- **THEN** the system refreshes it and the request succeeds

#### Scenario: Refresh rejected
- **WHEN** the Music Provider rejects the refresh token
- **THEN** the account is marked as needing re-linking, the Host is notified, and the Party's Player is Paused with a Party Log entry

#### Scenario: Concurrent refreshes
- **WHEN** several operations discover an expired token at once
- **THEN** only one refresh is performed and all use its result

#### Scenario: Instance credentials misconfigured
- **WHEN** a refresh fails because the instance's client credentials are wrong (for Spotify, `invalid_client`)
- **THEN** no Host account is marked as needing re-linking, the failure is treated as the Provider being unavailable, and refreshes succeed again once the credentials are fixed

### Requirement: Playlists
The system SHALL let a Music Provider list the playlists a Host can choose from, read a playlist's Tracks, and append Tracks to a playlist for the History Playlist. Playlist Tracks read for the Fallback Playlist SHALL be cached for a short period, and the cache SHALL be cleared when the Party's Fallback Playlist changes.

#### Scenario: Reading a Fallback Playlist
- **WHEN** a Host chooses a playlist as Fallback Playlist
- **THEN** its Tracks are read with their normalised metadata so Party rules can be applied

#### Scenario: Appending to the History Playlist
- **WHEN** a History Playlist is configured and a Track starts playing
- **THEN** the Track is appended to that Provider playlist

#### Scenario: History append fails
- **WHEN** appending to the History Playlist fails
- **THEN** the failure is recorded and retried later and does not interrupt playback

#### Scenario: Small Fallback Playlist
- **WHEN** a Fallback Playlist has too few eligible Tracks to fill the Queue and playback ticks repeatedly
- **THEN** the playlist is fetched from the Music Provider at most once per cache period
