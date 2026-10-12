## MODIFIED Requirements

### Requirement: Normalised Track metadata
The system SHALL expose Tracks with a provider-independent shape: Provider identifier, name, artists (with identifiers), album (with identifier), duration, explicit flag, ISRC where available, popularity from 0 to 100 where available, cover art URLs, and playability for the Party's market.

#### Scenario: Track returned from search
- **WHEN** a Member searches for Tracks
- **THEN** each result includes name, artists, album, duration, explicit flag, popularity, cover art and whether it is playable

#### Scenario: Missing optional metadata
- **WHEN** a Music Provider returns a Track without an ISRC, popularity or cover art
- **THEN** the Track is still returned, with those fields empty

### Requirement: Catalogue search
The system SHALL let any logged-in Member search the Party's Music Provider catalogue by text, returning Tracks in pages of 20 with a next page while the Provider has more. Search SHALL use the Host's Search account when the Music Provider supports one and it is linked and usable, else the Host's control account when it is linked and usable, else the instance-level credential.

#### Scenario: Search without a linked account
- **WHEN** a Member searches and the Host has linked no Music Provider account
- **THEN** results are still returned, using the instance credential

#### Scenario: Search with only a control account
- **WHEN** the Host has linked a Spotify control account but no Search account and a Member searches
- **THEN** the search is made with the control account's token

#### Scenario: Search with a Search account
- **WHEN** the Host of a Spotify Party has a usable Spotify Search account and a Member searches
- **THEN** the search is made with that account's token

#### Scenario: Search account needs re-linking
- **WHEN** the Host's Search account has been rejected by the Music Provider
- **THEN** search falls back to the control account, or the instance credential if there is none, and the Host is told to re-link it

#### Scenario: Next page
- **WHEN** a Member asks for the second page of results for a query that has more than 20 matches
- **THEN** results 21 to 40 are returned

#### Scenario: Provider unavailable
- **WHEN** the Music Provider is unreachable or rate limiting
- **THEN** the search returns a clear temporary-failure error and does not break the Party

#### Scenario: Instance search credential missing
- **WHEN** the instance search credential for a Music Provider is not configured
- **THEN** that Music Provider is reported as unavailable and cannot be selected for a Party

### Requirement: Host account linking
The system SHALL let only a user who may create or host Parties link a Music Provider account, through an OAuth authorisation started from the Link Spotify Account page. For Spotify there are two links: the control account, which asks for playlist and playback scopes, and, when an admin has enabled the Spotify Search provider, an optional Search account. After linking the user SHALL return to the page they started from. The Host SHALL be able to unlink either account at any time.

#### Scenario: Host links an account
- **WHEN** a Host completes the Music Provider OAuth flow for the control account
- **THEN** the account is linked to that Host and available to their Parties whose Player needs it

#### Scenario: Playback scopes requested
- **WHEN** the Spotify control link is started
- **THEN** the authorisation asks for the playlist scopes and for `user-read-playback-state` and `user-modify-playback-state`

#### Scenario: Return to the Party
- **WHEN** the Host starts linking from a Party's settings page and completes it
- **THEN** they return to that settings page with a success message

#### Scenario: Linking the Search account
- **WHEN** an admin has enabled Spotify Search and the Host links their Search account
- **THEN** it is stored as a separate linked account under the Spotify Search provider, with tokens handled like the control account

#### Scenario: Non-Host attempts to link
- **WHEN** a Member who is not a Host attempts to link a Music Provider account for a Party
- **THEN** the request is refused

#### Scenario: Player needs no account
- **WHEN** a Party uses a Player that does not need a linked account, such as a Soloist Player
- **THEN** the Party can go Live without any linked account

#### Scenario: Unlinking
- **WHEN** a Host unlinks their control account while a Party depends on it
- **THEN** the stored credentials are deleted and the affected Party is Paused with a Party Log entry

#### Scenario: Unlinking the Search account
- **WHEN** a Host unlinks their Search account
- **THEN** its credentials are deleted and search uses the control account, or the instance credential if there is none

### Requirement: Spotify Provider
The system SHALL provide a Spotify Music Provider implementing the contract. It SHALL search with the Host's Spotify Search account, else the Host's control account, else instance-level client credentials, and use instance-level client credentials for Track lookup, and the Host's control account for playlists and for Players that need one. Rate-limit backoff SHALL be kept per Spotify client application, so a rate limit on one application does not stop calls made with another.

#### Scenario: Spotify search
- **WHEN** a Member searches a Spotify Party
- **THEN** results come from Spotify and are normalised to Track metadata

#### Scenario: Spotify market filtering
- **WHEN** a Spotify Track is not playable in the Party's market
- **THEN** it is marked unplayable and cannot be requested

#### Scenario: Spotify rate limit
- **WHEN** Spotify responds with a rate limit
- **THEN** the system backs off for the advised period for that client application and surfaces a temporary-failure error

#### Scenario: Search application limited, control application not
- **WHEN** Spotify rate limits the Spotify Search application while the control application is not limited
- **THEN** searches made with Search accounts fail fast until the deadline, and polling and hand-offs made with the control account continue
