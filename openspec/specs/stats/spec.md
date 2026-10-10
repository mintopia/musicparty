# stats Specification

## Purpose
Synced from the archived v3-rewrite change.

## Requirements

### Requirement: Live Stats
The system SHALL maintain Live Stats for every Party, visible to its Members throughout the Party, including at least: top Tracks, top requesters, most upvoted Requests, most downvoted Requests and total time played. Live Stats MUST NOT be available to anonymous visitors.

#### Scenario: Member views stats
- **WHEN** a Member opens the stats view of a Live Party
- **THEN** they see the current top Tracks, top requesters, most upvoted and most downvoted Requests and total time played

#### Scenario: Non-member denied
- **WHEN** an anonymous visitor or a user who has not joined the Party requests its Live Stats
- **THEN** access is refused

#### Scenario: Visible in every state
- **WHEN** a Party is Paused or Ended
- **THEN** its Members can still view its stats

#### Scenario: Empty Party
- **WHEN** nothing has been played or requested yet
- **THEN** the stats view shows empty states rather than errors

### Requirement: Vote leaderboards
Live Stats SHALL include two leaderboards of the Party's Members, one ranked by upvotes received and one by downvotes received on their Requests, showing the top entries of each. Votes on system Requests (those with no Member) and on Pending, Rejected or Removed Requests MUST NOT count. Leaderboards are visible only to Members of the Party, never on the TV screen or to anonymous visitors. Cached Live Stats written before the leaderboards existed MUST still render.

#### Scenario: Members ranked by votes received
- **WHEN** Members have received upvotes and downvotes on their Requests
- **THEN** the stats view lists the Members with the most upvotes and the Members with the most downvotes, highest first

#### Scenario: Fallback Requests excluded
- **WHEN** a system Request from the Fallback Playlist receives Votes
- **THEN** no leaderboard entry is created for it

#### Scenario: No votes yet
- **WHEN** no counted Votes have been cast
- **THEN** both leaderboards show empty states

#### Scenario: Older cached stats
- **WHEN** a cached Live Stats payload lacks the leaderboard keys
- **THEN** the stats view still renders, with empty leaderboards

### Requirement: Real-time updates
Live Stats SHALL update in real time as Requests are made, Votes are cast and Plays complete, without the viewer reloading. Recalculation SHALL happen outside the request that caused it, and bursts of activity in a Party SHALL be coalesced into one recalculation, so that requesting, voting and rating are neither slowed down nor failed by Stats. Viewers SHALL see the update within about ten seconds. The data sent SHALL contain no member IDs or secrets beyond what is visible to Members in the Party.

#### Scenario: Vote updates stats
- **WHEN** a Member casts a Vote while another Member is viewing Live Stats
- **THEN** the viewer's most upvoted or most downvoted figures update without a reload

#### Scenario: Play completes
- **WHEN** a Track finishes playing
- **THEN** total time played and top Tracks update for connected viewers

#### Scenario: Burst of Votes
- **WHEN** fifty Votes are cast in a Party within a few seconds
- **THEN** Live Stats are recalculated once for that burst, and every Vote request completes without waiting for the recalculation

#### Scenario: Concurrent recalculation
- **WHEN** two recalculations for the same Party run at the same moment
- **THEN** both complete without error and the stored Live Stats reflect the latest data

### Requirement: Stats exclude non-counted activity
Stats SHALL be derived from the Party's recorded Requests, Votes and Plays. Rejected and Removed Requests MUST NOT count towards played statistics, and system Requests created from the Fallback Playlist SHALL be excluded from requester rankings.

#### Scenario: Fallback excluded from requesters
- **WHEN** a Track plays from the Fallback Playlist
- **THEN** it counts towards time played and top Tracks but appears in no requester ranking

### Requirement: Party Export
The system SHALL provide a Party Export for an Ended Party: a versioned JSON dataset suitable for external Wrapped-style reporting. It SHALL include the Party's metadata, Plays, Requests, Votes aggregates, Ratings and Member nicknames, and SHALL carry an explicit schema version. The format SHALL be documented in the OpenAPI specification.

#### Scenario: Export an Ended Party
- **WHEN** an authorised caller requests the Party Export of an Ended Party
- **THEN** a JSON document is returned containing a schema version and the Party's Plays, Requests, Vote aggregates, Ratings and nicknames

#### Scenario: Party not Ended
- **WHEN** the export is requested for a Live or Paused Party
- **THEN** the request is refused with an error stating the Party has not ended

#### Scenario: Version stability
- **WHEN** the export format changes in a breaking way
- **THEN** the schema version is incremented and earlier versions remain documented

#### Scenario: Privacy
- **WHEN** a Party Export is produced
- **THEN** it contains no email addresses, provider credentials or tokens

### Requirement: Export access
A Party Export SHALL be retrievable by the Party's Host, by an instance admin, and by an Integration Token. Other Members, Moderators and anonymous callers MUST be refused.

#### Scenario: Host exports
- **WHEN** the Host requests the export of their Ended Party
- **THEN** it is returned

#### Scenario: Integration Token exports
- **WHEN** a request carries a valid Integration Token
- **THEN** the export of any Ended Party is returned

#### Scenario: Moderator refused
- **WHEN** a Moderator requests the export
- **THEN** the request is refused

#### Scenario: Members do not get the JSON export
- **WHEN** a non-staff Member requests the JSON Party Export
- **THEN** the request is refused, the Member playlist download being a separate feature

### Requirement: Member playlist download
The system SHALL let any non-banned Member of an Ended Party download the Party's Plays as a CSV file. The CSV SHALL have one row per Play in played order with the columns position, played at, title, artists, album, requester display name, score and Music Provider track URL. Cells that could be interpreted as spreadsheet formulas SHALL be neutralised. The JSON Party Export remains restricted to the Host, instance admins and Integration Tokens.

#### Scenario: Member downloads the playlist
- **WHEN** a Member requests the playlist download of an Ended Party
- **THEN** a CSV is returned with a header row and one row per Play in played order, including the requester display name

#### Scenario: Party has no Plays
- **WHEN** a Member downloads the playlist of an Ended Party that played nothing
- **THEN** the CSV contains only the header row

#### Scenario: Party not Ended
- **WHEN** the playlist download is requested for a Live or Paused Party
- **THEN** the request is refused with an error stating the Party has not ended

#### Scenario: Non-member or banned Member refused
- **WHEN** a non-member, banned Member or anonymous caller requests the playlist download
- **THEN** the request is refused

#### Scenario: Formula injection
- **WHEN** a Track title or other text cell begins with `=`, `+`, `-`, `@`, tab or carriage return
- **THEN** the cell is prefixed with a single quote

### Requirement: Reopened Party exports
If a Host reopens an Ended Party, its export SHALL be unavailable until it is Ended again, and a later export SHALL include Plays from the whole Party history.

#### Scenario: Reopen then end
- **WHEN** an Ended Party is reopened, plays more Tracks, and is Ended again
- **THEN** the new export includes both earlier and later Plays
