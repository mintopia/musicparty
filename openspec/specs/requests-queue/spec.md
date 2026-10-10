# requests-queue Specification

## Purpose
Synced from the archived v3-rewrite change.

## Requirements

### Requirement: Request lifecycle
A Request SHALL be in exactly one of the states Pending, Queued, Up Next, Playing, Played, Rejected or Removed. The permitted transitions are: Pending to Queued, Rejected or Removed; Queued to Up Next, Rejected or Removed; Up Next to Playing or Removed; Playing to Played. Played, Rejected and Removed are terminal. A Request created without a holding rule SHALL start as Queued.

#### Scenario: Normal progression
- **WHEN** a Request is accepted, selected as Up Next, begins playing and finishes
- **THEN** it passes through Queued, Up Next, Playing and Played in that order

#### Scenario: Invalid transition
- **WHEN** an action tries to move a Played Request back to Queued
- **THEN** the system refuses the transition

#### Scenario: Pending approval is opt-in
- **WHEN** a Party has no holding rule or approval setting and a Request is accepted
- **THEN** the Request is Queued, not Pending

### Requirement: Requesting a Track
A logged-in, non-Banned Member SHALL be able to request a Track from the Party's Music Provider while the Party is Live and accepting Requests. The Track SHALL come from the Party's Music Provider catalogue. The requester SHALL automatically cast an upvote on their own Request.

#### Scenario: Successful request
- **WHEN** a Guest requests a Track that passes all request rules
- **THEN** a Queued Request exists for that Track with the Guest as requester and the Guest's upvote

#### Scenario: Requests disabled
- **WHEN** a Member requests a Track while the Party has Requests disabled
- **THEN** the Request is refused with the reason

#### Scenario: Banned requester
- **WHEN** a Banned Member requests a Track
- **THEN** the Request is refused

### Requirement: Request rules
The system SHALL evaluate each new Request against the Party's rules and refuse it with a specific reason when any rule fails. The rules are: Requests accepted; maximum Requests per Member (VIP and Host exempt); minimum and maximum Track length; explicit filter; Blocklist; no-repeat interval; the Track is not already Up Next; and no similar Track already in the Queue. Requests SHALL be refused at the first failing rule and the refusal reason SHALL be shown to the requester.

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

#### Scenario: Already Up Next
- **WHEN** a Member requests the Track currently locked as Up Next
- **THEN** the Request is refused as already Up Next

#### Scenario: Similar Track already queued
- **WHEN** a Member requests a Track that the system considers similar to one already in the Queue (for example, the same recording on a different release)
- **THEN** the request is treated as a duplicate

### Requirement: Duplicate requests become upvotes
When a Member requests a Track that is already Queued or Pending in the Party, the system SHALL NOT create a second Request. Instead it SHALL record an upvote from that Member on the existing Request, subject to the Member's Vote entitlement, and tell the Member this happened.

#### Scenario: Duplicate becomes upvote
- **WHEN** a Member requests a Track already Queued by someone else
- **THEN** no new Request is created and the Member's upvote is added to the existing Request

#### Scenario: Duplicate by the same Member
- **WHEN** a Member requests a Track they have already requested or voted on
- **THEN** no new Request or additional Vote is created and the Member is told the Track is already queued

#### Scenario: Banned Member duplicate
- **WHEN** a Banned Member requests a Track already in the Queue
- **THEN** no Vote is recorded

### Requirement: Blocklist
Each Party SHALL have a Blocklist whose entries match Tracks by name, track identifier, artist name, artist identifier, album name, album identifier or ISRC. An entry MAY use a regular expression for name matches, MAY be disabled and MAY carry notes. A Request matching any enabled entry SHALL be refused. The Host and Moderators SHALL be able to manage the Blocklist. Blocklist changes SHALL be recorded in the Party Log.

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

### Requirement: Holding Requests as Pending
A Party MAY be configured so that Requests, or Requests held by a Request Rule, are Pending until approved. The Host and Moderators SHALL be able to approve a Pending Request, which becomes Queued, or reject it, which becomes Rejected. The requester SHALL be notified of the outcome. Pending Requests SHALL NOT be eligible for selection and SHALL NOT appear in the public Queue.

#### Scenario: Approval
- **WHEN** a Moderator approves a Pending Request
- **THEN** it becomes Queued and the requester is notified

#### Scenario: Rejection
- **WHEN** a Moderator rejects a Pending Request
- **THEN** it becomes Rejected and the requester is notified

#### Scenario: Pending hidden from the public
- **WHEN** a Request is Pending
- **THEN** only its requester, the Host and Moderators can see it

### Requirement: Removing Requests
The Host and Moderators SHALL be able to remove a Queued or Pending Request, moving it to Removed. A Member SHALL be able to remove their own Queued or Pending Request. A Request that is Up Next MAY be removed only by the Host or a Moderator and only if the Player is able to withdraw it; otherwise the removal SHALL be refused. A Playing Request MUST NOT be removed (skipping applies to playback, not the Request).

#### Scenario: Moderator removes
- **WHEN** a Moderator removes a Queued Request
- **THEN** it becomes Removed and the action is recorded in the Party Log

#### Scenario: Requester withdraws
- **WHEN** a Member removes their own Queued Request
- **THEN** it becomes Removed

#### Scenario: Cannot remove another's
- **WHEN** a Guest attempts to remove another Member's Request
- **THEN** the system refuses

### Requirement: Votes
A Member SHALL be able to cast one Vote, up or down, per Request, and to change or retract it. Banned Members MUST NOT vote. Members MUST NOT vote on Requests that are not Queued. Downvotes SHALL be disableable per Party. When downvotes are enabled, a Member SHALL be limited to the Party's configured number of downvotes per hour, after which further downvotes are refused until the allowance recovers. Retracting or changing a Vote SHALL restore allowance consistent with the rolling hour rule.

#### Scenario: Casting a Vote
- **WHEN** a Guest upvotes a Queued Request
- **THEN** the Request's score increases by one

#### Scenario: Changing a Vote
- **WHEN** a Guest changes an upvote to a downvote
- **THEN** the Request's score decreases by two relative to before and the Guest has one Vote on it

#### Scenario: One Vote per Request
- **WHEN** a Guest upvotes a Request they have already upvoted
- **THEN** the score does not change

#### Scenario: Downvotes disabled
- **WHEN** downvotes are disabled and a Member attempts to downvote
- **THEN** the system refuses

#### Scenario: Downvote cap reached
- **WHEN** a Member who has used their hourly downvote allowance attempts another downvote
- **THEN** the system refuses and states when they can downvote again

#### Scenario: Voting on an Up Next Request
- **WHEN** a Member votes on the Up Next Request
- **THEN** the Vote is refused because the Up Next Request is locked

### Requirement: Score
A Request's score SHALL equal the sum of its Votes (up as +1, down as -1) plus adjustments applied by Score Modifiers. Scores SHALL be visible to Members and update in real time.

#### Scenario: Score computation
- **WHEN** a Request has 5 upvotes, 2 downvotes and a Score Modifier adjustment of +3
- **THEN** its score is 6

### Requirement: Ratings
A Member SHALL be able to like or dislike a Play, once per Play, and to change or retract the rating. Banned Members MUST NOT rate. Ratings SHALL be included in Live Stats and the Party Export.

#### Scenario: Liking a Play
- **WHEN** a Member likes the currently Playing Track
- **THEN** a Rating of like by that Member exists for that Play

#### Scenario: Changing a Rating
- **WHEN** a Member who liked a Play dislikes it
- **THEN** the Member has a single Rating of dislike for that Play

#### Scenario: Banned Member rating
- **WHEN** a Banned Member attempts to rate a Play
- **THEN** the system refuses

### Requirement: Queue top-up from the Fallback Playlist
While a Party is Live, the system SHALL keep the Queue topped up to a configured minimum number of Queued Requests (default 5) using Tracks from the Fallback Playlist. Fallback Playlist Tracks SHALL be chosen in shuffled order, SHALL skip Tracks queued or played recently, SHALL pass the Party's rules, and SHALL become Requests with no requester. Fallback Requests SHALL be subject to Votes like any other Request.

#### Scenario: Queue runs low
- **WHEN** the Queue holds fewer than the minimum number of Requests
- **THEN** Fallback Requests with no requester are added until the minimum is met

#### Scenario: Skipping recent Tracks
- **WHEN** a Fallback Playlist Track was queued or played recently
- **THEN** it is not chosen for top-up while other eligible Tracks exist

#### Scenario: Blocked fallback Track
- **WHEN** a Fallback Playlist Track matches the Blocklist
- **THEN** it is never added

#### Scenario: Requests displace fallback
- **WHEN** a Member's Request has a higher score than Fallback Requests
- **THEN** it is selected ahead of them under the Party's selection mode

#### Scenario: Not topped up when not Live
- **WHEN** a Party is Paused or Ended
- **THEN** no Fallback Requests are added
