## ADDED Requirements

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

### Requirement: Real-time updates
Live Stats SHALL update in real time as Requests are made, Votes are cast and Plays complete, without the viewer reloading. The data sent SHALL contain no member IDs or secrets beyond what is visible to Members in the Party.

#### Scenario: Vote updates stats
- **WHEN** a Member casts a Vote while another Member is viewing Live Stats
- **THEN** the viewer's most upvoted or most downvoted figures update without a reload

#### Scenario: Play completes
- **WHEN** a Track finishes playing
- **THEN** total time played and top Tracks update for connected viewers

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

### Requirement: Reopened Party exports
If a Host reopens an Ended Party, its export SHALL be unavailable until it is Ended again, and a later export SHALL include Plays from the whole Party history.

#### Scenario: Reopen then end
- **WHEN** an Ended Party is reopened, plays more Tracks, and is Ended again
- **THEN** the new export includes both earlier and later Plays
