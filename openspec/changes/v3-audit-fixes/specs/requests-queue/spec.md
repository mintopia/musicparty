## MODIFIED Requirements

### Requirement: Requesting a Track
A logged-in, non-Banned Member SHALL be able to request a Track from the Party's Music Provider while the Party is Live and accepting Requests. The Track SHALL come from the Party's Music Provider catalogue. The requester SHALL automatically cast an upvote on their own Request. The Party state, Party settings and the Member's Ban status SHALL be evaluated as they are when the Request is committed, not as they were when it was submitted.

#### Scenario: Successful request
- **WHEN** a Guest requests a Track that passes all request rules
- **THEN** a Queued Request exists for that Track with the Guest as requester and the Guest's upvote

#### Scenario: Requests disabled
- **WHEN** a Member requests a Track while the Party has Requests disabled
- **THEN** the Request is refused with the reason

#### Scenario: Banned requester
- **WHEN** a Banned Member requests a Track
- **THEN** the Request is refused

#### Scenario: Ban during a pending request
- **WHEN** a Moderator bans a Member while that Member's request is waiting on the Music Provider
- **THEN** the Request is refused and not added to the Queue

#### Scenario: Setting changed during a pending request
- **WHEN** the Host disables explicit Tracks while a request for an explicit Track is waiting on the Music Provider
- **THEN** the Request is refused by the explicit filter

### Requirement: Votes
A Member SHALL be able to cast one Vote, up or down, per Request, and to change or retract it. Banned Members MUST NOT vote. Members MUST NOT vote on Requests that are not Queued, or in a Party that is Ended. Downvotes SHALL be disableable per Party. When downvotes are enabled, a Member SHALL be limited to the Party's configured number of downvotes per hour, after which further downvotes are refused until the allowance recovers. Retracting or changing a Vote SHALL restore allowance consistent with the rolling hour rule. Ban status and Party state SHALL be checked against current data at the moment the Vote is committed.

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

#### Scenario: Concurrent downvotes
- **WHEN** a Member sends several downvotes at the same moment that together exceed their hourly allowance
- **THEN** only the downvotes within the allowance are accepted

#### Scenario: Voting on an Up Next Request
- **WHEN** a Member votes on the Up Next Request
- **THEN** the Vote is refused because the Up Next Request is locked

#### Scenario: Voting after the Party ends
- **WHEN** a Member votes on a Request that is still Queued in a Party that has been Ended
- **THEN** the Vote is refused, the score does not change and nothing is broadcast

### Requirement: Ratings
A Member SHALL be able to like or dislike a Play, once per Play, and to change or retract the rating. Rating the currently Playing Track SHALL rate its Play, so there is a single record of ratings for each Play. Banned Members MUST NOT rate, and ratings MUST NOT be accepted in an Ended Party. Ratings SHALL be included in Live Stats and the Party Export.

#### Scenario: Liking a Play
- **WHEN** a Member likes the currently Playing Track
- **THEN** a Rating of like by that Member exists for that Play

#### Scenario: Changing a Rating
- **WHEN** a Member who liked a Play dislikes it
- **THEN** the Member has a single Rating of dislike for that Play

#### Scenario: Banned Member rating
- **WHEN** a Banned Member attempts to rate a Play
- **THEN** the system refuses

#### Scenario: Rating while playing shows in history
- **WHEN** Members like a Track while it is playing and the Track then ends
- **THEN** the played history shows those likes for that Play, and a Member cannot add a second like to it

#### Scenario: Rating after the Party ends
- **WHEN** a Member rates a Play in an Ended Party
- **THEN** the system refuses
