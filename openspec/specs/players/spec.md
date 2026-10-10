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
A Party SHALL use exactly one Player. Each Player kind SHALL declare its Compatibility with Music Providers, and the system SHALL refuse to pair a Music Provider with an incompatible Player.

#### Scenario: Incompatible pairing
- **WHEN** a Host selects a Player kind not compatible with the Party's Music Provider
- **THEN** the system refuses and states the compatible options

#### Scenario: Changing Player
- **WHEN** a Host changes a Party's Player
- **THEN** the change is recorded in the Party Log and the old Player stops receiving commands

### Requirement: Feed Modes and the Up Next hand-off
Each Player SHALL declare a Feed Mode. In "just-in-time" mode the system SHALL, shortly before the current Track ends, select the next Request, lock it as Up Next and give it to the Player. In "ahead" mode the system SHALL keep exactly one Up Next Request loaded in the Player and refill it after each Track change. A locked Up Next SHALL NOT change when Votes change.

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
A Host SHALL be able to issue, list and revoke Player Tokens for their own Party. A Player Token SHALL be bound to one Party, grant only the abilities Players need, be shown in full only at creation, and be stored so it cannot be recovered.

#### Scenario: Issue
- **WHEN** a Host issues a Player Token
- **THEN** the full token is shown once and later listings show only a label and last-used time

#### Scenario: Revoke
- **WHEN** a Host revokes a Player Token
- **THEN** any connection or call using it is rejected from then on and the live connection is closed

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
The Browser Player SHALL play audio in the Host's browser and be event-driven using the "just-in-time" Feed Mode. It SHALL need a linked Host account for the in-browser playback token, and that token SHALL never be sent on a public channel, in a public API response or to any client other than the Host's own authenticated session.

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

### Requirement: Fake Player for tests
The system SHALL include a fake Player usable in automated tests that can emit playback events, report any Feed Mode, record the commands sent to it and simulate disconnects.

#### Scenario: Driving a Party in a test
- **WHEN** a test emits Track changes from the fake Player
- **THEN** the Queue, Plays and Up Next hand-off behave as they would for a real Player

#### Scenario: Asserting commands
- **WHEN** the hand-off runs in a test
- **THEN** the fake Player records exactly which Request was handed off and when
