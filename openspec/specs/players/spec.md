# players Specification

## Purpose
Synced from the archived v3-rewrite change.

## Requirements

### Requirement: Player contract
The system SHALL define a Player contract through which Music Party observes and controls what plays for a Party: reporting the current Track, playback status and position, signalling Track changes, receiving the Up Next Request, and exposing which controls it supports. Music Party SHALL own the Queue and be the single source of truth for it.

#### Scenario: Player reports playback
- **WHEN** a Player observes a Track change
- **THEN** the Party's now-playing state, Plays and Queue advance accordingly

#### Scenario: Unsupported control
- **WHEN** a Host or Moderator requests a control the Player does not support
- **THEN** the system refuses with a clear "unsupported by this Player" error

### Requirement: One Player per Party and Compatibility
A Party SHALL use exactly one Player. Each Player kind SHALL declare its Compatibility with Music Providers, and the system SHALL refuse to pair a Music Provider with an incompatible Player. A change of Player SHALL take effect in every web and background worker without a restart.

#### Scenario: Incompatible pairing
- **WHEN** a Host selects a Player kind not compatible with the Party's Music Provider
- **THEN** the system refuses and states the compatible options

#### Scenario: Changing Player
- **WHEN** a Host changes a Party's Player
- **THEN** the change is recorded in the Party Log and the old Player stops receiving commands

#### Scenario: Change seen by running workers
- **WHEN** a Host changes a Party from the Polling Player to the Soloist Player while background workers are running
- **THEN** the next Player message or playback tick for that Party is handled by the Soloist Player without restarting any worker

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

### Requirement: Polling Player
The Polling Player SHALL observe playback by periodically asking the Music Provider what is playing on the Host's linked account, using the "ahead" Feed Mode. It SHALL require a linked Host account and SHALL be compatible with Spotify.

#### Scenario: Track change detected
- **WHEN** a poll shows a different Track than the last poll
- **THEN** the Play is recorded, the Queue advances and a new Up Next is loaded

#### Scenario: Polling without a linked account
- **WHEN** the Host's account is unlinked or needs re-linking
- **THEN** polling stops, the Party is Paused and the Host is told why

#### Scenario: Provider errors while polling
- **WHEN** polls fail transiently
- **THEN** the system retries with back-off and does not record false Track changes

#### Scenario: Nothing playing
- **WHEN** a poll shows nothing playing on the Host's account
- **THEN** the Party's now-playing state shows idle

### Requirement: Polling Player playback controls
The Polling Player SHALL support the play, pause, skip, seek and volume controls by driving the Host's Spotify device through the Spotify Web API using the Host's linked account, under the shared Spotify rate-limit backoff. Volume SHALL be a whole percentage from 0 to 100. The Browser Player SHALL remain unsupported for every control.

#### Scenario: Control reaches the Host's device
- **WHEN** an authorised caller sends play, pause, skip, seek or volume for a Party that uses the Polling Player
- **THEN** the matching command is sent to the Host's Spotify device and the call succeeds

#### Scenario: No active device or restriction
- **WHEN** Spotify reports no active device, or refuses the command for a restriction such as Premium being required
- **THEN** the call is refused with a clear conflict error naming the reason, not a server error

#### Scenario: Spotify rate limiting
- **WHEN** Spotify is rate limiting requests
- **THEN** the call is refused with a 429 error stating when to retry, without calling Spotify again until the back-off passes

#### Scenario: Host account unavailable
- **WHEN** the Host's account is not linked or Spotify rejects its credentials
- **THEN** the call is refused as the Player being disconnected

#### Scenario: Browser Player controls
- **WHEN** a control is sent for a Party that uses the Browser Player
- **THEN** the call is refused with the "unsupported by this Player" error and the UI shows the controls as disabled

### Requirement: Webhook
The system SHALL accept an authenticated inbound HTTP call, made with a Player Token, that tells a Party's Polling Player to check playback immediately. A Webhook SHALL NOT be able to control playback.

#### Scenario: Valid Webhook
- **WHEN** a call with a valid Player Token for the Party is received
- **THEN** the Polling Player checks playback immediately and the call succeeds

#### Scenario: Missing, invalid or revoked token
- **WHEN** the call has no token, an invalid token, a revoked token or a token for another Party
- **THEN** it is rejected as unauthenticated or forbidden and nothing is polled

#### Scenario: Webhook flood
- **WHEN** Webhooks arrive faster than the permitted rate
- **THEN** excess calls are rate limited and at most one extra check is queued

#### Scenario: Webhook for a non-polling Party
- **WHEN** the Party's Player is not a Polling Player
- **THEN** the call is rejected with a clear error

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

### Requirement: Soloist Player
The Soloist Player SHALL be backed by a Soloist proxy that connects in to Music Party over the Pusher protocol and joins a private Player channel for the Party, authenticated by a Player Token. It SHALL use the "just-in-time" Feed Mode and SHALL NOT need a linked Host account.

#### Scenario: Connect and authenticate
- **WHEN** a Soloist proxy subscribes to the Party's private Player channel with a valid Player Token
- **THEN** the subscription is authorised and the Player is marked connected

#### Scenario: Invalid token
- **WHEN** a client subscribes with an invalid, revoked or other-Party Player Token
- **THEN** the subscription is refused

#### Scenario: Wrapped native frames
- **WHEN** the proxy sends Soloist's native event frames, wrapped unchanged inside Pusher client events on the Player channel
- **THEN** Music Party parses the original frames and updates playback state from them

#### Scenario: Malformed frame
- **WHEN** a frame cannot be parsed or fails validation
- **THEN** it is discarded and counted, and the connection and Party are unaffected

#### Scenario: Commands sent back
- **WHEN** a Host, or the Up Next hand-off, issues play, pause, skip, seek, volume, activate or add-to-queue
- **THEN** the command is delivered over the same Player channel as a Soloist-native command frame

#### Scenario: State requested on connect
- **WHEN** a Soloist Player connects
- **THEN** Music Party sends a get_state command so it has the current playback state without waiting for the next event

#### Scenario: Track added just in time
- **WHEN** the just-in-time hand-off locks an Up Next Request
- **THEN** Music Party sends an add_to_queue command for that Track and treats the locked Up Next as final, since Soloist cannot remove queued items

#### Scenario: Reconnect
- **WHEN** the proxy reconnects after a drop
- **THEN** Music Party requests state again and reconciles the Queue without duplicating the Up Next

#### Scenario: No domain logic in the realtime server
- **WHEN** a Player frame arrives
- **THEN** the realtime server only validates and queues it for background processing, and Queue and Play changes happen in background jobs

### Requirement: Soloist connection health
The system SHALL determine Soloist connection health without relying on application heartbeats, using subscription state, the frequency of received frames and a liveness check, and SHALL show the Host whether the Player is connected, stale or disconnected.

#### Scenario: Silent connection while playing
- **WHEN** the Party is playing and no frame has arrived for longer than the expected cadence plus a margin
- **THEN** the Player is marked stale and Music Party requests state to confirm

#### Scenario: Long pause
- **WHEN** playback is paused for a long time with no frames
- **THEN** the Player is not marked disconnected while the subscription remains alive

#### Scenario: Confirmed disconnect
- **WHEN** the subscription ends or liveness checks fail
- **THEN** the Player is marked disconnected and the Party's now-playing state shows idle

### Requirement: Party Log for Player connection
The system SHALL write a Party Log entry whenever a Player connects or disconnects, and when a Player Token is issued or revoked.

#### Scenario: Connect logged
- **WHEN** a Soloist Player connects
- **THEN** a Party Log entry records the connection with its time

#### Scenario: Disconnect logged
- **WHEN** a Soloist Player disconnects or is marked disconnected
- **THEN** a Party Log entry records it with the reason when known

#### Scenario: Flapping connection
- **WHEN** a connection drops and reconnects repeatedly within a short window
- **THEN** the log records each change without flooding notifications to the Host

### Requirement: Soloist proxy Pusher relay support
The Soloist proxy (repository musicparty-soloist) SHALL gain support for relaying over the Pusher protocol: connecting to a Reverb server, authenticating a private channel with a Player Token, forwarding Soloist's frames unchanged as client events, delivering commands received on the channel to Soloist, reconnecting with back-off, and replaying current state after reconnecting.

#### Scenario: Relay configured for Music Party
- **WHEN** the proxy is configured with a Music Party host, a Party and a Player Token
- **THEN** it connects, subscribes to the private Player channel and forwards Soloist frames

#### Scenario: Existing raw relay unaffected
- **WHEN** the proxy is configured with the existing raw WebSocket relay
- **THEN** its behaviour is unchanged

#### Scenario: Token rejected
- **WHEN** Music Party refuses the proxy's channel authorisation
- **THEN** the proxy reports the failure and backs off rather than retrying rapidly

### Requirement: Browser Player
The Browser Player SHALL play audio in the Host's browser and be event-driven using the "just-in-time" Feed Mode. It SHALL need a linked Host account for the in-browser playback token, and that token SHALL never be sent on a public channel, in a public API response or to any client other than the Host's own authenticated session. Only one browser tab SHALL hold the Player role at a time, even when tabs claim it at the same moment.

#### Scenario: Playback in the Host's browser
- **WHEN** the Host opens the Party's player page and starts the Browser Player
- **THEN** the browser registers as the playback device and plays the Up Next Request when it is handed off

#### Scenario: Token never public
- **WHEN** any public channel, anonymous endpoint or Moderator or Guest session is inspected
- **THEN** no Music Provider token is present

#### Scenario: Browser closes
- **WHEN** the Host closes the player page
- **THEN** the Player is marked disconnected, a Party Log entry is written and playback stops

#### Scenario: Second browser tab
- **WHEN** the Host opens the player page in a second tab
- **THEN** only one tab holds the Player role and the other is told so

#### Scenario: Simultaneous claims
- **WHEN** two tabs claim the Player role at the same moment
- **THEN** exactly one tab holds it

### Requirement: Fake Player for tests
The system SHALL include a fake Player usable in automated tests that can emit playback events, report any Feed Mode, record the commands sent to it and simulate disconnects.

#### Scenario: Driving a Party in a test
- **WHEN** a test emits Track changes from the fake Player
- **THEN** the Queue, Plays and Up Next hand-off behave as they would for a real Player

#### Scenario: Asserting commands
- **WHEN** the hand-off runs in a test
- **THEN** the fake Player records exactly which Request was handed off and when

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
