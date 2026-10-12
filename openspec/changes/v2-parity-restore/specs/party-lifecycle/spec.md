## MODIFIED Requirements

### Requirement: Party creation
The system SHALL allow a user holding the create-party instance role to create a Party. The creator SHALL become the Party's Host. A Party SHALL be bound to exactly one Music Provider and paired with one Player that has Compatibility with that Music Provider. The create form SHALL offer the v2 fields: name, Music Provider, Player, downvotes, the raffle selection mode and the request rules. A Spotify Party whose Player needs a linked account, or whose instance has Spotify Search enabled, SHALL require the needed Spotify links first.

#### Scenario: Creating a Party
- **WHEN** a user with the create-party role creates a Party choosing a Music Provider and a compatible Player
- **THEN** the Party is created, the user is its Host, and it has a unique party code

#### Scenario: User without the create-party role
- **WHEN** a user without the create-party role (and who is not an admin) attempts to create a Party
- **THEN** the system refuses and no Party is created

#### Scenario: Incompatible pairing
- **WHEN** a Host pairs a Music Provider with a Player kind that has no Compatibility with it
- **THEN** the system rejects the pairing with a validation error

#### Scenario: Spotify link missing
- **WHEN** a user without a linked Spotify control account chooses Spotify with the Polling Player
- **THEN** the form explains that Spotify must be linked first, links to the Link Spotify Account page, and refuses to create the Party until it is linked

#### Scenario: Search link required when enabled
- **WHEN** an admin has enabled the Spotify Search provider and the user has linked only the control account
- **THEN** creating a Spotify Party is refused until the Search account is linked

#### Scenario: Settings chosen at creation
- **WHEN** a user creates a Party with downvotes off and the raffle selection mode
- **THEN** the new Party has downvotes disabled and uses weighted selection

### Requirement: Playlist, Mod and theme settings
The Host SHALL be able to set the Fallback Playlist, the History Playlist, the enabled Mods and the Party Theme. The playlist pickers SHALL list the Host's playlists from the Music Provider and SHALL also accept a pasted playlist link or id. The Fallback Playlist SHALL be a single Party setting: choosing it from the playlist picker, the settings page or the API SHALL change the same value that Queue top-up and the Fallback Playlist gate use.

#### Scenario: Changing the Fallback Playlist
- **WHEN** the Host selects a different Fallback Playlist
- **THEN** the Fallback Playlist gate is re-validated against it

#### Scenario: Fallback chosen through the playlist picker
- **WHEN** the Host chooses a Fallback Playlist through the playlist picker
- **THEN** Queue top-up draws from that playlist and the Party's settings show it as the Fallback Playlist

#### Scenario: Other playlist
- **WHEN** the Host pastes a link to a public playlist they do not own as the Fallback Playlist
- **THEN** it is accepted and validated like a listed playlist

#### Scenario: Go Live after choosing a Fallback Playlist
- **WHEN** a new Party's Host links Spotify, picks a Fallback Playlist with at least 20 playable Tracks on the settings page and presses Go Live
- **THEN** the Party goes Live without using the API

#### Scenario: History Playlist chosen
- **WHEN** the Host picks one of their playlists as History Playlist
- **THEN** Tracks are appended to it as they start, and choosing none stops appending

## ADDED Requirements

### Requirement: Party settings page
The Host SHALL be able to edit every Party setting from one settings page laid out in v2's sections: general (name), playback (Player kind, device, Fallback and History Playlists), requests and restrictions (request rules, downvotes, downvotes per hour, raffle with its hint, approval, freeze while Paused), display (TV QR code) and Mods. Settings the API can change SHALL be editable here.

#### Scenario: Rename
- **WHEN** the Host changes the Party name on the settings page
- **THEN** the new name is shown on every Party page and the change is in the Party Log

#### Scenario: Downvote settings
- **WHEN** the Host turns downvotes on and sets 3 downvotes per hour
- **THEN** Members can downvote up to 3 times an hour

#### Scenario: Raffle
- **WHEN** the Host chooses raffle selection
- **THEN** the page explains that more upvotes make a Track more likely, not certain, to play next, and the Party uses weighted selection

#### Scenario: Moderator
- **WHEN** a Moderator opens the settings page
- **THEN** access is refused

### Requirement: TV QR code setting
Each Party SHALL have a "show QR code on TV" setting, on by default. When it is off, the TV screen SHALL NOT show the join QR code or the join link.

#### Scenario: Default
- **WHEN** a Party is created
- **THEN** its TV screen shows the QR code

#### Scenario: Hidden
- **WHEN** the Host turns the setting off while the TV screen is open
- **THEN** the QR code disappears from the TV screen without a reload
