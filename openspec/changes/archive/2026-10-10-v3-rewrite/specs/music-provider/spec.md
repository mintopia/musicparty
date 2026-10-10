## ADDED Requirements

### Requirement: Music Provider contract
The system SHALL define a Music Provider contract that every Music Provider implements, covering Track search, Track metadata lookup, playlist reading and writing, and Host account linking. Callers SHALL depend only on this contract and never on a specific service.

#### Scenario: Provider used through the contract
- **WHEN** a Party's Music Provider is asked to search, fetch a Track or read a playlist
- **THEN** the behaviour is identical in shape for every Music Provider, returning the normalised Track metadata defined by this capability

#### Scenario: Unsupported capability is declared
- **WHEN** a Music Provider cannot support an optional capability such as playlist writing
- **THEN** it declares so and callers receive a clear "unsupported" result rather than a failure

### Requirement: One Music Provider per Party
A Party SHALL be bound to exactly one Music Provider, and its Queue, Fallback Playlist and History Playlist SHALL contain only Tracks from that Music Provider.

#### Scenario: Track from another Provider
- **WHEN** a Member requests or a Host selects a Track belonging to a different Music Provider than the Party's
- **THEN** the system rejects it with a validation error

#### Scenario: Provider cannot change while Tracks exist
- **WHEN** a Host attempts to change a Party's Music Provider while it has Requests or a Fallback Playlist from the current one
- **THEN** the system refuses the change

### Requirement: Normalised Track metadata
The system SHALL expose Tracks with a provider-independent shape: Provider identifier, name, artists (with identifiers), album (with identifier), duration, explicit flag, ISRC where available, cover art URLs, and playability for the Party's market.

#### Scenario: Track returned from search
- **WHEN** a Member searches for Tracks
- **THEN** each result includes name, artists, album, duration, explicit flag, cover art and whether it is playable

#### Scenario: Missing optional metadata
- **WHEN** a Music Provider returns a Track without an ISRC or cover art
- **THEN** the Track is still returned, with those fields empty

### Requirement: Catalogue search
The system SHALL let any logged-in Member search the Party's Music Provider catalogue by text, returning a bounded, paged list of Tracks. Search SHALL use an instance-level credential and SHALL NOT use any user's linked account.

#### Scenario: Search without a linked account
- **WHEN** a Member searches and no Host has linked a Music Provider account
- **THEN** results are still returned

#### Scenario: Provider unavailable
- **WHEN** the Music Provider is unreachable or rate limiting
- **THEN** the search returns a clear temporary-failure error and does not break the Party

#### Scenario: Instance search credential missing
- **WHEN** the instance search credential for a Music Provider is not configured
- **THEN** that Music Provider is reported as unavailable and cannot be selected for a Party

### Requirement: Playlists
The system SHALL let a Music Provider list the playlists a Host can choose from, read a playlist's Tracks, and append Tracks to a playlist for the History Playlist.

#### Scenario: Reading a Fallback Playlist
- **WHEN** a Host chooses a playlist as Fallback Playlist
- **THEN** its Tracks are read with their normalised metadata so Party rules can be applied

#### Scenario: Appending to the History Playlist
- **WHEN** a History Playlist is configured and a Play completes
- **THEN** the Track is appended to that Provider playlist

#### Scenario: History append fails
- **WHEN** appending to the History Playlist fails
- **THEN** the failure is recorded and retried later and does not interrupt playback

### Requirement: Host account linking
The system SHALL let only a Host link a Music Provider account, and only for Players that need one. Linking SHALL be an OAuth authorisation, and the Host SHALL be able to unlink it at any time.

#### Scenario: Host links an account
- **WHEN** a Host completes the Music Provider OAuth flow
- **THEN** the account is linked to that Host and available to their Parties whose Player needs it

#### Scenario: Non-Host attempts to link
- **WHEN** a Member who is not a Host attempts to link a Music Provider account for a Party
- **THEN** the request is refused

#### Scenario: Player needs no account
- **WHEN** a Party uses a Player that does not need a linked account, such as a Soloist Player
- **THEN** the Party can go Live without any linked account

#### Scenario: Unlinking
- **WHEN** a Host unlinks their account while a Party depends on it
- **THEN** the stored credentials are deleted and the affected Party is Paused with a Party Log entry

### Requirement: Encrypted token storage and refresh
The system SHALL store Music Provider access and refresh tokens encrypted at rest, SHALL refresh access tokens before they expire or on an authorisation failure, and SHALL NOT expose a token in any API response, broadcast, log or page.

#### Scenario: Token refreshed transparently
- **WHEN** a request needs a linked account and its access token has expired
- **THEN** the system refreshes it and the request succeeds

#### Scenario: Refresh rejected
- **WHEN** the Music Provider rejects the refresh token
- **THEN** the account is marked as needing re-linking, the Host is notified, and the Party's Player is Paused with a Party Log entry

#### Scenario: Concurrent refreshes
- **WHEN** several operations discover an expired token at once
- **THEN** only one refresh is performed and all use its result

### Requirement: Spotify Provider
The system SHALL provide a Spotify Music Provider implementing the contract, using instance-level client credentials for search and Track lookup and the Host's linked account for playlists and for Players that need one.

#### Scenario: Spotify search
- **WHEN** a Member searches a Spotify Party
- **THEN** results come from Spotify and are normalised to Track metadata

#### Scenario: Spotify market filtering
- **WHEN** a Spotify Track is not playable in the Party's market
- **THEN** it is marked unplayable and cannot be requested

#### Scenario: Spotify rate limit
- **WHEN** Spotify responds with a rate limit
- **THEN** the system backs off for the advised period and surfaces a temporary-failure error

### Requirement: Fake Music Provider for tests
The system SHALL include a fake Music Provider usable in automated tests, with deterministic catalogue, playlists and failure injection, proving the contract holds without any real service.

#### Scenario: Fake provider in a test
- **WHEN** a test configures a Party with the fake Music Provider
- **THEN** search, Track lookup and playlists behave per the contract with no network access

#### Scenario: Failure injection
- **WHEN** a test instructs the fake Music Provider to fail or rate limit
- **THEN** the system behaves as it would for a real Provider failure
