## MODIFIED Requirements

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
Each Player SHALL declare a Feed Mode. In "just-in-time" mode the system SHALL, shortly before the current Track ends, select the next Request, lock it as Up Next and give it to the Player. In "ahead" mode the system SHALL keep exactly one Up Next Request loaded in the Player and refill it after each Track change. A locked Up Next SHALL NOT change when Votes change. If handing the Up Next Request to the Player fails for any reason, the hand-off SHALL be retried with increasing delays from a configured schedule. Each failed attempt SHALL be recorded in the Party Log. When the final delay has been used, the system SHALL stop retrying and record that it gave up. A Player reconnect or a Host playback control SHALL reset the retries.

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
- **WHEN** sending the Up Next Request to the Player fails, for example because the realtime server is restarting
- **THEN** the Request stays Up Next and becomes eligible to be sent again, a Party Log entry records the attempt and when the next one will be made, and no attempt is made before then

#### Scenario: Hand-off retries exhausted
- **WHEN** the attempt after the final delay in the schedule also fails
- **THEN** the system stops retrying that Request and records in the Party Log that it gave up

#### Scenario: Retry after reconnect
- **WHEN** the Player reconnects or the Host uses a playback control after the hand-off has failed
- **THEN** the hand-off is attempted again immediately

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

## ADDED Requirements

### Requirement: Ordered Player message handling
Messages received from a Party's Player SHALL be applied one at a time and in the order they were received, so that a later message never has its effect undone by an earlier one.

#### Scenario: Burst of frames
- **WHEN** a Soloist Player sends a track change followed immediately by a position update
- **THEN** the Party's playback state reflects the new Track after both are processed

#### Scenario: Pause then play
- **WHEN** a Player reports paused and then playing in quick succession
- **THEN** the Party's playback state ends as playing
