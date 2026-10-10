## MODIFIED Requirements

### Requirement: Player Tokens
A Host SHALL be able to issue, list and revoke Player Tokens for their own Party. A Player Token SHALL be bound to one Party, grant only the abilities Players need, be shown in full only at creation, be stored so it cannot be recovered, and expire after a configured lifetime. Revoking a token SHALL stop the system accepting frames from, or sending commands to, a connection using it.

#### Scenario: Issue
- **WHEN** a Host issues a Player Token
- **THEN** the full token is shown once and later listings show only a label, last-used time and expiry

#### Scenario: Revoke
- **WHEN** a Host revokes a Player Token
- **THEN** any connection or call using it is rejected from then on and the live connection is closed

#### Scenario: Revoked Player keeps sending
- **WHEN** a Player whose token was revoked sends a frame on its still-open connection
- **THEN** the frame is not applied and the connection is closed

#### Scenario: Expiry
- **WHEN** a Player Token reaches its expiry
- **THEN** it can no longer authorise a connection or an API call, and it is pruned

#### Scenario: Moderator cannot issue
- **WHEN** a Moderator or Guest tries to issue or revoke a Player Token
- **THEN** the request is forbidden

### Requirement: Ordered Player message handling
Messages received from a Party's Player SHALL be applied one at a time and in the order they were received, so that a later message never has its effect undone by an earlier one. A message that is skipped because it expired or arrived out of order SHALL be counted in the metrics and recorded in the Party Log, at most once a minute per Party.

#### Scenario: Burst of frames
- **WHEN** a Soloist Player sends a track change followed immediately by a position update
- **THEN** the Party's playback state reflects the new Track after both are processed

#### Scenario: Pause then play
- **WHEN** a Player reports paused and then playing in quick succession
- **THEN** the Party's playback state ends as playing

#### Scenario: Frame dropped
- **WHEN** a buffered frame expires before it is applied
- **THEN** the dropped-frame metric increases and the Party Log records that frames were dropped

### Requirement: Feed Modes and the Up Next hand-off
Each Player SHALL declare a Feed Mode. In "just-in-time" mode the system SHALL, shortly before the current Track ends, select the next Request, lock it as Up Next and give it to the Player. In "ahead" mode the system SHALL keep exactly one Up Next Request loaded in the Player and refill it after each Track change. A locked Up Next SHALL NOT change when Votes change. If handing the Up Next Request to the Player fails in a way that shows the Player never received it, the hand-off SHALL be retried with increasing delays from a configured schedule. If the outcome is unknown, such as a timeout or a server error after sending, the system SHALL NOT resend until the Player's reported state shows the Track is not queued. Each failed attempt SHALL be recorded in the Party Log. When the final delay has been used, the system SHALL stop retrying and record that it gave up. A Player reconnect or a Host playback control SHALL reset the retries.

#### Scenario: Just-in-time hand-off
- **WHEN** the current Track has about 15 seconds remaining under a just-in-time Player
- **THEN** the next Request is selected, becomes Up Next and is sent to the Player once

#### Scenario: Ahead refill
- **WHEN** an "ahead" Player reports a Track change
- **THEN** a new Up Next is selected and loaded so exactly one is waiting

#### Scenario: Votes after lock
- **WHEN** Votes change the order of the Queue after a Request is Up Next
- **THEN** the Up Next Request remains unchanged

#### Scenario: Hand-off not duplicated
- **WHEN** the hand-off is triggered twice for the same Track end
- **THEN** the Up Next Request is only sent to the Player once

#### Scenario: Nothing eligible
- **WHEN** the Queue is empty and no Fallback Track is eligible
- **THEN** playback stops and the Provider's own autoplay is not allowed to take over

#### Scenario: Hand-off fails
- **WHEN** sending the Up Next Request to the Player fails because the Player is disconnected or the connection was refused
- **THEN** the Request stays Up Next and becomes eligible to be sent again, a Party Log entry records the attempt and when the next one will be made, and no attempt is made before then

#### Scenario: Outcome unknown
- **WHEN** sending the Up Next Request times out or the Provider returns a server error, and the Provider had in fact queued the Track
- **THEN** the Track is not sent again and plays once

#### Scenario: Outcome unknown and not queued
- **WHEN** sending the Up Next Request times out and the Player's next reported state shows the Track is not queued
- **THEN** the hand-off is retried under the backoff schedule

#### Scenario: Hand-off retries exhausted
- **WHEN** the attempt after the final delay in the schedule also fails
- **THEN** the system stops retrying that Request and records in the Party Log that it gave up

#### Scenario: Retry after reconnect
- **WHEN** the Player reconnects or the Host uses a playback control after the hand-off has failed
- **THEN** the hand-off is attempted again immediately

## ADDED Requirements

### Requirement: Playback ticks per Party
The periodic playback check SHALL run separately for each Live Party, so that a slow or failing Party does not delay any other. Checks for the same Party SHALL NOT overlap, and a stuck check SHALL release its hold on the Party after a bounded time. A check SHALL NOT call the Music Provider unless it has a hand-off to make or a playlist read that is not cached.

#### Scenario: Slow Party
- **WHEN** one Party's check waits on a slow Provider call
- **THEN** other Parties' checks run on schedule

#### Scenario: Overlapping ticks
- **WHEN** a Party's check is still running when the next tick is due
- **THEN** no second check for that Party starts

#### Scenario: Stuck check
- **WHEN** a Party's check is killed without finishing
- **THEN** checks for that Party resume within the lock's expiry

#### Scenario: Idle ticks
- **WHEN** a Live Party with a Playing Track and nothing to hand off is checked 60 times
- **THEN** the Music Provider is not called

### Requirement: Scheduled Mod actions per Party
Scheduled Mod actions SHALL run separately for each Live Party with the same isolation, no-overlap and lock-expiry rules as playback checks.

#### Scenario: Failing Mod in one Party
- **WHEN** a scheduled Mod action throws in one Party
- **THEN** scheduled actions in other Parties still run
