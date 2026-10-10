## MODIFIED Requirements

### Requirement: Playlist, Mod and theme settings
The Host SHALL be able to set the Fallback Playlist, the History Playlist, the enabled Mods and the Party Theme. The Fallback Playlist SHALL be a single Party setting: choosing it from the playlist picker, the settings page or the API SHALL change the same value that Queue top-up and the Fallback Playlist gate use.

#### Scenario: Changing the Fallback Playlist
- **WHEN** the Host selects a different Fallback Playlist
- **THEN** the Fallback Playlist gate is re-validated against it

#### Scenario: Fallback chosen through the playlist picker
- **WHEN** the Host chooses a Fallback Playlist through the playlist picker
- **THEN** Queue top-up draws from that playlist and the Party's settings show it as the Fallback Playlist

### Requirement: History Playlist
When a History Playlist is configured, the system SHALL append each Track to it when the Track starts playing. The History Playlist SHALL be optional.

#### Scenario: Appending played Tracks
- **WHEN** a Track starts playing in a Party with a History Playlist configured
- **THEN** the Track is appended to that playlist at the Music Provider

#### Scenario: No History Playlist
- **WHEN** a Track starts playing in a Party with no History Playlist configured
- **THEN** no playlist is modified and playback continues normally

#### Scenario: Provider failure
- **WHEN** appending to the History Playlist fails
- **THEN** playback is not interrupted and the failure is recorded in the Party Log

### Requirement: Party Log
The system SHALL maintain a single Party Log for each Party recording: Bans and unbans, Requests approved, rejected or removed, settings changes, Player changes, Player connects and disconnects, failed and abandoned hand-offs to the Player, act-as-Host sessions, and automatic actions taken by Mods. Each entry SHALL record the time, the acting Member or system component, the action and its subject. The Party Log SHALL be visible only to the Host and Moderators and SHALL be append-only.

#### Scenario: Moderation action logged
- **WHEN** a Moderator removes a Request
- **THEN** a Party Log entry records the Moderator, the Request and the time

#### Scenario: Mod auto-action logged
- **WHEN** a Mod rejects a Request automatically
- **THEN** the Party Log records the Mod as the actor and the reason

#### Scenario: Guest cannot view
- **WHEN** a Guest or anonymous visitor requests the Party Log
- **THEN** the system refuses access

#### Scenario: Log retained after Party ends
- **WHEN** a Party is Ended
- **THEN** its Party Log remains available to the Host and Moderators

#### Scenario: Host sees admin access
- **WHEN** an admin enters and later leaves act-as-Host mode for a Party
- **THEN** the Host sees both entries in that Party's Party Log, flagged as act-as-Host
