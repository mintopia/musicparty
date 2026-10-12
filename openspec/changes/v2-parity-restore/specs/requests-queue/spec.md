## MODIFIED Requirements

### Requirement: Duplicate requests become upvotes
When a Member requests a Track that is already Queued or Pending in the Party, the system SHALL NOT create a second Request. Instead it SHALL record an upvote from that Member on the existing Request, subject to the Member's Vote entitlement, and tell the Member this happened. A Track is the same when its provider Track id matches, or when both Tracks have an ISRC and the ISRCs match. Track, artist and album names SHALL NOT be compared.

#### Scenario: Duplicate becomes upvote
- **WHEN** a Member requests a Track already Queued by someone else
- **THEN** no new Request is created and the Member's upvote is added to the existing Request

#### Scenario: Duplicate by the same Member
- **WHEN** a Member requests a Track they have already requested or voted on
- **THEN** no new Request or additional Vote is created and the Member is told the Track is already queued

#### Scenario: Banned Member duplicate
- **WHEN** a Banned Member requests a Track already in the Queue
- **THEN** no Vote is recorded

#### Scenario: Same recording, other release
- **WHEN** a Member requests a Track whose ISRC equals that of a Queued Request with a different Track id
- **THEN** it is treated as a duplicate

#### Scenario: Similar name only
- **WHEN** a Member requests "Song (Remastered)" while "Song" by the same artist is Queued with a different Track id and ISRC
- **THEN** a new Request is created

### Requirement: Blocklist
Each Party SHALL have a Blocklist whose entries match Tracks by name, track identifier, artist name, artist identifier, album name, album identifier or ISRC. An entry of any type MAY use a regular expression, MAY be disabled and MAY carry notes. A non-regex entry matches the whole value, ignoring case for names, and `*` in it matches any run of characters. A Request matching any enabled entry SHALL be refused. A regular expression that fails while being evaluated SHALL be treated as a match (the Request is refused) and the failure SHALL be logged and recorded in the Party Log. The Host and Moderators SHALL be able to add, edit, disable and delete entries, with deletion confirmed first. Blocklist changes SHALL be recorded in the Party Log. Checking many Tracks against the Blocklist SHALL load the Blocklist once, not once per Track.

#### Scenario: Blocked artist
- **WHEN** a Member requests a Track by an artist on the Blocklist
- **THEN** the Request is refused citing the Blocklist

#### Scenario: Regex entry
- **WHEN** an enabled entry uses a regular expression that matches a Track name
- **THEN** a Request for that Track is refused

#### Scenario: Wildcard entry
- **WHEN** an enabled non-regex track name entry is `*christmas*`
- **THEN** a Request for "Last Christmas" is refused and a Request for "Christmas Eve Blues (live)" is refused

#### Scenario: Literal characters
- **WHEN** an enabled non-regex entry is `AC/DC (live)`
- **THEN** only that exact name, ignoring case, matches, and the brackets are not treated as a pattern

#### Scenario: Regex on an identifier
- **WHEN** an enabled ISRC entry uses the regular expression `/^GBAYE\d+/`
- **THEN** any Track whose ISRC starts with GBAYE is refused

#### Scenario: Disabled entry
- **WHEN** a Blocklist entry is disabled
- **THEN** it does not cause any Request to be refused

#### Scenario: ISRC match
- **WHEN** a Track's ISRC matches an enabled entry
- **THEN** a Request for any release of that recording is refused

#### Scenario: Invalid regular expression
- **WHEN** a Host saves a Blocklist entry with an invalid regular expression
- **THEN** the system rejects the entry with a validation error

#### Scenario: Regular expression fails at runtime
- **WHEN** a saved regular expression hits the backtracking limit while checking a Track
- **THEN** the Track is treated as blocked and the Party Log records the failing entry

#### Scenario: Editing an entry
- **WHEN** a Moderator changes an entry's value, type or notes
- **THEN** the new value applies to subsequent Requests and the Party Log records the old and new values

#### Scenario: Deleting an entry
- **WHEN** a Moderator chooses delete on an entry
- **THEN** they are asked to confirm, and the entry is deleted only after confirmation

#### Scenario: Fallback top-up against a large playlist
- **WHEN** the Queue is topped up from a 500-Track Fallback Playlist
- **THEN** the Blocklist is read from the database once for the whole top-up

## ADDED Requirements

### Requirement: Queue list
The Queue page SHALL show the first 20 Queued Requests in Queue order and load the next 20 as the Member scrolls to the end, while live updates keep every loaded entry current. Beside the Queue heading it SHALL offer a search box that opens the Party's catalogue search for the entered text. The Host and Moderators SHALL have a remove action on every Queued Request, and a Member on their own Requests where removal is allowed.

#### Scenario: Long Queue
- **WHEN** a Member opens a Queue of 75 Requests
- **THEN** 20 are shown and scrolling to the end loads 20 more until all 75 are shown

#### Scenario: Live update while scrolled
- **WHEN** a Vote reorders Requests that are already loaded
- **THEN** the loaded list reorders without losing the scroll position or the number loaded

#### Scenario: Staff remove
- **WHEN** a Moderator removes another Member's Queued Request from the Queue list
- **THEN** it becomes Removed and disappears from the Queue for everyone

#### Scenario: Search from the Queue
- **WHEN** a Member types "queen" in the box beside the Queue heading and submits
- **THEN** the Search section opens with catalogue results for "queen"

### Requirement: Vote presentation
Vote controls SHALL show the Member's current Vote with filled icons. When downvotes are disabled, the upvote SHALL be shown as a heart. When a Party uses weighted selection, the Queue heading SHALL read "Upcoming Songs" with the hint that more upvotes make a Track more likely to play. Vote errors SHALL be shown centred over the Queue.

#### Scenario: Upvote-only Party
- **WHEN** a Member views the Queue of a Party with downvotes disabled
- **THEN** each Request shows a heart instead of up and down arrows

#### Scenario: Refused Vote
- **WHEN** a Member's downvote is refused because the hourly cap is reached
- **THEN** a centred message states when they can downvote again

### Requirement: Search results
Each search result SHALL show cover, name, artists, album, duration, explicit flag, popularity as stars and a link to the Track at the Music Provider. Before the Member clicks, each result SHALL show whether it can be requested and, if not, why. A result already Queued SHALL offer up, down and retract Vote controls instead of a request button.

#### Scenario: Ineligible result
- **WHEN** a search result is longer than the Party's maximum Track length
- **THEN** its request button is disabled with the reason

#### Scenario: Vote from results
- **WHEN** a Member searches for a Track that is already Queued and presses upvote on the result
- **THEN** their upvote is recorded on the Queued Request

#### Scenario: Empty results
- **WHEN** a search returns nothing
- **THEN** the page shows the v2 empty-state wording

### Requirement: Song detail
Members SHALL be able to open a song detail page for any Request or Play in the Party. It SHALL show the Track, requester, requested and sent times, its Votes by Member, its Ratings by Member, other times the Track appeared in the Party, and a View on Spotify link. The Host and Moderators SHALL also have a Moderate action that opens a Blocklist entry prefilled for the Track.

#### Scenario: Votes on a song
- **WHEN** a Member opens the detail page of a Request with three Votes
- **THEN** the page lists the three Members with their Vote direction and time

#### Scenario: Moderate shortcut
- **WHEN** a Moderator chooses Moderate on a song detail page
- **THEN** the Blocklist form opens prefilled with the Track's identifier and name

#### Scenario: Other Party
- **WHEN** a Member requests the song detail of a Request in a Party they are not a Member of
- **THEN** access is refused

### Requirement: Played history browsing
The History page SHALL list the Party's Plays and unsent Requests with filters by name, artist, album and type (all, played, unsent), sortable columns, a choice of page size, and Track names linking to song detail. Its default type SHALL include unsent Requests as v2 did.

#### Scenario: Sort by votes
- **WHEN** a Member sorts History by score, highest first
- **THEN** the Plays are ordered by score and the order holds across pages

#### Scenario: Unsent Requests
- **WHEN** a Member filters History by unsent
- **THEN** Requests that were Queued but never played are listed
