## ADDED Requirements

### Requirement: Player availability
A Player kind SHALL be available for a Music Provider only when it is registered in code, an admin has enabled it, and it has Compatibility with that Music Provider. Only available Player kinds SHALL be offered or accepted when creating a Party or changing its Player. Availability SHALL be read per request, without a restart. A Party already using a Player kind that is later disabled SHALL keep using it.

#### Scenario: Disabled Player hidden and rejected
- **WHEN** an admin has disabled the Browser Player and a user submits Create Party with Spotify and `player_kind` set to `browser`
- **THEN** the Create Party form does not offer the Browser Player, the submission is rejected with a validation error on `player_kind`, and no Party is created

#### Scenario: Only compatible enabled Players offered
- **WHEN** Spotify is available and the Polling, Browser and Soloist Players are enabled
- **THEN** choosing Spotify offers exactly those three Player kinds, and no Player kind without Compatibility with Spotify

#### Scenario: Switching to a disabled Player refused
- **WHEN** an admin has disabled the Soloist Player and a Host tries to change a Party from the Polling Player to the Soloist Player
- **THEN** the change is refused with a validation error and the Party keeps the Polling Player

#### Scenario: Existing Party keeps a disabled Player
- **WHEN** an admin disables the Polling Player while a Party using it is Live
- **THEN** that Party keeps playing through the Polling Player, its playback ticks continue, and its settings page still shows the Polling Player as the current choice

#### Scenario: Player toggle applies without a restart
- **WHEN** an admin enables the Soloist Player while workers are running
- **THEN** the next Create Party or Party settings request handled by any already-running worker offers it, with no worker restarted or reloaded
