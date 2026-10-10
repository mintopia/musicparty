## MODIFIED Requirements

### Requirement: Score
A Request's Score SHALL equal the sum of its Votes (up as +1, down as -1) plus adjustments applied by Score Modifiers. The Score shown to Members SHALL be the same value the Queue is ordered by and selection uses. The Queue SHALL be shown ordered by Score, then by oldest request time. Scores SHALL be visible to Members and update in real time. Under weighted selection, the Queue SHALL still be shown ordered by Score, and the page SHALL say that the next Track is drawn at random in proportion to Score, so the top Request may not play next.

#### Scenario: Score computation
- **WHEN** a Request has 5 upvotes, 2 downvotes and a Score Modifier adjustment of +3
- **THEN** its Score is 6

#### Scenario: Displayed order matches deterministic selection
- **WHEN** a Score Modifier lifts a Request with fewer Votes above another, under deterministic selection
- **THEN** the Queue shows the lifted Request first with its adjusted Score, and it is the one selected

#### Scenario: Weighted selection
- **WHEN** the Party uses weighted selection
- **THEN** the Queue is shown ordered by Score with a note that selection is random in proportion to Score

#### Scenario: Same everywhere
- **WHEN** the same Queue is read from the Party page, the API and the public channel
- **THEN** each Request has the same Score and position in all three

### Requirement: Request rules
The system SHALL evaluate each new Request against the Party's rules and refuse it with a specific reason when any rule fails. The rules are: Requests accepted; maximum Requests per Member (VIP and Host exempt); minimum and maximum Track length; explicit filter; Blocklist; no-repeat interval, counting the Playing Track as played; the Track is not already Up Next; and no similar Track already in the Queue. Requests SHALL be refused at the first failing rule and the refusal reason SHALL be shown to the requester.

#### Scenario: Per-Member limit
- **WHEN** a Guest at their maximum number of active Requests requests another Track
- **THEN** the Request is refused citing the limit

#### Scenario: Host exempt from limit
- **WHEN** the Host at the maximum number of active Requests requests another Track
- **THEN** the Request is accepted

#### Scenario: Track too long
- **WHEN** a Member requests a Track longer than the Party's maximum length
- **THEN** the Request is refused citing the length limit

#### Scenario: Explicit filter
- **WHEN** the explicit filter is on and a Member requests an explicit Track
- **THEN** the Request is refused citing the explicit filter

#### Scenario: No-repeat interval
- **WHEN** a Member requests a Track that was Played within the no-repeat interval
- **THEN** the Request is refused citing when it was last played

#### Scenario: Track playing now
- **WHEN** a Member requests the Track that is Playing and the Party has a no-repeat interval
- **THEN** the Request is refused under the no-repeat rule

#### Scenario: Already Up Next
- **WHEN** a Member requests the Track currently locked as Up Next
- **THEN** the Request is refused as already Up Next

#### Scenario: Similar Track already queued
- **WHEN** a Member requests a Track that the system considers similar to one already in the Queue (for example, the same recording on a different release)
- **THEN** the request is treated as a duplicate

### Requirement: Blocklist
Each Party SHALL have a Blocklist whose entries match Tracks by name, track identifier, artist name, artist identifier, album name, album identifier or ISRC. An entry MAY use a regular expression for name matches, MAY be disabled and MAY carry notes. A Request matching any enabled entry SHALL be refused. A regular expression that fails while being evaluated SHALL be treated as a match (the Request is refused) and the failure SHALL be logged and recorded in the Party Log. The Host and Moderators SHALL be able to manage the Blocklist. Blocklist changes SHALL be recorded in the Party Log. Checking many Tracks against the Blocklist SHALL load the Blocklist once, not once per Track.

#### Scenario: Blocked artist
- **WHEN** a Member requests a Track by an artist on the Blocklist
- **THEN** the Request is refused citing the Blocklist

#### Scenario: Regex entry
- **WHEN** an enabled entry uses a regular expression that matches a Track name
- **THEN** a Request for that Track is refused

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

#### Scenario: Fallback top-up against a large playlist
- **WHEN** the Queue is topped up from a 500-Track Fallback Playlist
- **THEN** the Blocklist is read from the database once for the whole top-up

## ADDED Requirements

### Requirement: One active Track per Party
A Party SHALL have at most one Up Next Request and at most one Playing Request at any time, enforced by the database as well as by locking.

#### Scenario: Concurrent promotion
- **WHEN** two processes try to make different Requests Up Next for the same Party at once
- **THEN** one succeeds and the other fails without changing any Request
