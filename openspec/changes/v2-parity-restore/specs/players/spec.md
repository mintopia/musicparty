## MODIFIED Requirements

### Requirement: One Player per Party and Compatibility
A Party SHALL use exactly one Player. Each Player kind SHALL declare its Compatibility with Music Providers, and the system SHALL refuse to pair a Music Provider with an incompatible Player. The Host SHALL be able to change the Player kind from the Party settings page as well as the API. A change of Player SHALL take effect in every web and background worker without a restart.

#### Scenario: Incompatible pairing
- **WHEN** a Host selects a Player kind not compatible with the Party's Music Provider
- **THEN** the system refuses and states the compatible options

#### Scenario: Changing Player
- **WHEN** a Host changes a Party's Player
- **THEN** the change is recorded in the Party Log and the old Player stops receiving commands

#### Scenario: Changing Player from settings
- **WHEN** the Host picks the Soloist Player instead of the Polling Player on the settings page and saves
- **THEN** the Party uses the Soloist Player and the settings page offers Player Tokens instead of the device picker

#### Scenario: Change seen by running workers
- **WHEN** a Host changes a Party from the Polling Player to the Soloist Player while background workers are running
- **THEN** the next Player message or playback tick for that Party is handled by the Soloist Player without restarting any worker

### Requirement: Polling Player playback controls
The Polling Player SHALL support the previous, play, pause, skip, seek and volume controls by driving the Host's Spotify device through the Spotify Web API using the Host's linked account, under the shared Spotify rate-limit backoff. Commands SHALL target the Party's chosen playback device when one is set. Volume SHALL be a whole percentage from 0 to 100. The Browser Player SHALL remain unsupported for every control.

#### Scenario: Control reaches the Host's device
- **WHEN** an authorised caller sends previous, play, pause, skip, seek or volume for a Party that uses the Polling Player
- **THEN** the matching command is sent to the Host's Spotify device and the call succeeds

#### Scenario: Chosen device targeted
- **WHEN** the Party has a chosen playback device and the Host presses Play
- **THEN** the play command names that device

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

## ADDED Requirements

### Requirement: Previous control
The Host SHALL be able to send a Previous control through the UI and the API. If the current Track has played for at least 3 seconds, Previous SHALL restart it. Otherwise it SHALL replay the Track of the Party's most recent Play, recorded as a new Play of the same Request without changing Votes or the Queue. The Up Next Request SHALL stay locked and play after the replay.

#### Scenario: Restart
- **WHEN** the Host presses Previous 40 seconds into a Track
- **THEN** the Track restarts from the beginning and no new Play is recorded

#### Scenario: Back to the last Track
- **WHEN** the Host presses Previous 1 second into a Track that followed another Play
- **THEN** the previous Track plays again, a Play is recorded for it, and the Up Next Request is unchanged

#### Scenario: Soloist Player
- **WHEN** the Host presses Previous for a Party that uses the Soloist Player
- **THEN** a previous command is sent to the Soloist

#### Scenario: Not allowed for Moderators
- **WHEN** a Moderator or Guest sends Previous
- **THEN** the request is forbidden

### Requirement: Polling Player device selection
For a Party with the Polling Player, the Host SHALL be able to list the Spotify devices visible to their control account and choose one as the Party's playback device, or choose none. With no device chosen, Spotify's active device SHALL be used. A chosen device that Spotify no longer lists SHALL cause a conflict error naming the device and a Party Log entry.

#### Scenario: Pick a device
- **WHEN** the Host opens the device picker
- **THEN** it lists the device names and types Spotify returns for the Host's account, and saving one records it in the Party Log

#### Scenario: Device gone
- **WHEN** the chosen device is switched off and the Host presses Play
- **THEN** the call is refused with a conflict error naming the device, and the Party Log records it

#### Scenario: Account cannot list devices
- **WHEN** the Host's control account was linked without the playback scopes
- **THEN** the picker asks the Host to re-link Spotify

### Requirement: Player Tokens page
The Host SHALL have a Player Tokens page for their Party that lists tokens with label, last-used time and expiry, issues a token with a label and shows it in full once with a copy action, and revokes a token after confirmation.

#### Scenario: Issue from the page
- **WHEN** the Host issues a token labelled "Soloist PC"
- **THEN** the page shows the full token once, and after leaving the page only the label, last-used time and expiry are shown

#### Scenario: Revoke from the page
- **WHEN** the Host confirms revoking a token
- **THEN** it disappears from the list and a Player using it is disconnected

### Requirement: No forced playback
The system SHALL NOT start or transfer playback on any device by itself. Playback starts only through a Host playback control or the Player. No setting, schedule or command SHALL force playback.

#### Scenario: Idle Party
- **WHEN** a Live Polling Player Party has had nothing playing on its chosen device for 15 minutes
- **THEN** the system does not start playback, and the Party's now-playing state shows idle
