# party-lifecycle Specification

## Purpose
Synced from the archived v3-rewrite change.

## Requirements

### Requirement: Party creation
The system SHALL allow a user holding the create-party instance role to create a Party. The creator SHALL become the Party's Host. A Party SHALL be bound to exactly one Music Provider and paired with one Player that has Compatibility with that Music Provider.

#### Scenario: Creating a Party
- **WHEN** a user with the create-party role creates a Party choosing a Music Provider and a compatible Player
- **THEN** the Party is created, the user is its Host, and it has a unique party code

#### Scenario: User without the create-party role
- **WHEN** a user without the create-party role (and who is not an admin) attempts to create a Party
- **THEN** the system refuses and no Party is created

#### Scenario: Incompatible pairing
- **WHEN** a Host pairs a Music Provider with a Player kind that has no Compatibility with it
- **THEN** the system rejects the pairing with a validation error

### Requirement: Party code
Each Party SHALL have a party code of 4 uppercase letters that is unique among Parties on the instance. Party code lookup SHALL be case-insensitive on input.

#### Scenario: Code format
- **WHEN** a Party is created
- **THEN** its party code consists of exactly 4 uppercase letters and does not collide with any existing Party's code

#### Scenario: Lowercase entry
- **WHEN** a visitor enters a party code in lowercase
- **THEN** the system resolves it to the matching Party

### Requirement: Party states
A Party SHALL be in exactly one of the states Live, Paused or Ended. A Live Party SHALL be playing and accepting Requests. A Paused Party SHALL remain visible to its Members with its Player idle, and the Host MAY configure whether Requests are frozen while Paused. An Ended Party SHALL be read-only history and statistics.

#### Scenario: Pausing a Live Party
- **WHEN** the Host pauses a Live Party
- **THEN** the Party becomes Paused, playback stops, and the Party remains visible to Members

#### Scenario: Requests while Paused
- **WHEN** a Member requests a Track in a Paused Party whose settings freeze Requests
- **THEN** the Request is refused with an explanation that Requests are frozen

#### Scenario: Requests to an Ended Party
- **WHEN** a Member attempts to request, vote or rate in an Ended Party
- **THEN** the system refuses the action

### Requirement: Ending and reopening a Party
The Host SHALL be able to end a Party and SHALL be able to reopen an Ended Party. Ending a Party SHALL stop playback and make the Party read-only. Reopening SHALL return the Party to Paused so the Host can go Live again subject to the Fallback Playlist gate.

#### Scenario: Ending a Party
- **WHEN** the Host ends a Live or Paused Party
- **THEN** the Party becomes Ended, playback stops, and no further Requests, Votes or Ratings are accepted

#### Scenario: Reopening
- **WHEN** the Host reopens an Ended Party
- **THEN** the Party becomes Paused and its history and statistics are retained

#### Scenario: Non-Host cannot end
- **WHEN** a Moderator or Guest attempts to end or reopen a Party
- **THEN** the system refuses the action

### Requirement: Fallback Playlist gate
A Party SHALL NOT be allowed to go Live unless its Fallback Playlist contains at least 20 playable Tracks that pass the Party's rules. The Party's rules for this check are the Blocklist, the explicit filter, the length limits and the no-repeat window; per-Member limits SHALL NOT be applied. The system SHALL re-validate whenever the Fallback Playlist or those rules change.

#### Scenario: Insufficient playable Tracks
- **WHEN** the Host attempts to go Live and the Fallback Playlist has fewer than 20 playable Tracks passing the Party's rules
- **THEN** the system refuses, and reports how many playable Tracks were found and how many are required

#### Scenario: Sufficient playable Tracks
- **WHEN** the Host attempts to go Live and the Fallback Playlist has at least 20 playable Tracks passing the Party's rules
- **THEN** the Party goes Live

#### Scenario: Rules change invalidates the gate
- **WHEN** the Host changes the Blocklist or length limits so fewer than 20 Fallback Playlist Tracks remain playable
- **THEN** the system warns the Host that the Fallback Playlist no longer meets the requirement

#### Scenario: Unavailable Tracks
- **WHEN** a Fallback Playlist Track is unavailable in the Music Provider's catalogue
- **THEN** it is not counted as playable

### Requirement: Fallback Playlist exhaustion
While a Party is Live, if the Queue is empty and the Fallback Playlist has no eligible Track, the system SHALL first warn the Host, then SHALL re-allow Tracks excluded only by the no-repeat window, and only if still nothing is eligible SHALL stop playback. The system MUST NOT allow the Music Provider's own autoplay to take over playback.

#### Scenario: Approaching exhaustion
- **WHEN** eligible Fallback Playlist Tracks run low while the Party is Live
- **THEN** the Host is warned and the warning is recorded in the Party Log

#### Scenario: Recent Plays re-allowed
- **WHEN** no Track is eligible only because of the no-repeat window
- **THEN** recently played Tracks become eligible again so playback continues

#### Scenario: Nothing eligible
- **WHEN** the Queue is empty and no Track is eligible even after re-allowing recent Plays
- **THEN** playback stops, the Party remains Live, and the Host is notified

#### Scenario: No provider autoplay
- **WHEN** the Queue is empty and playback stops
- **THEN** the Music Provider's autoplay or radio does not start playing unrelated Tracks

### Requirement: Party settings access
The Host SHALL be able to configure a Party's settings. Moderators and Guests SHALL NOT be able to change settings. Every settings change SHALL be recorded in the Party Log.

#### Scenario: Host changes a setting
- **WHEN** the Host changes the maximum Requests per Member
- **THEN** the new value applies to subsequent Requests and a Party Log entry records who changed it, the old value and the new value

#### Scenario: Moderator cannot change settings
- **WHEN** a Moderator attempts to change a Party setting
- **THEN** the system refuses the change

### Requirement: Request rule settings
The Host SHALL be able to set whether Requests are accepted, the maximum Requests per Member, minimum and maximum Track length, the explicit filter and the no-repeat interval.

#### Scenario: Invalid value
- **WHEN** the Host sets a minimum Track length greater than the maximum Track length
- **THEN** the system rejects the change with a validation error

### Requirement: Voting and approval settings
The Host SHALL be able to set whether downvotes are enabled, the hourly downvote cap, whether Requests are held as Pending for approval, the selection mode, and whether Requests are frozen while Paused.

#### Scenario: Disabling downvotes
- **WHEN** the Host disables downvotes
- **THEN** subsequent downvotes are refused

### Requirement: Playlist, Mod and theme settings
The Host SHALL be able to set the Fallback Playlist, the History Playlist, the enabled Mods and the Party Theme.

#### Scenario: Changing the Fallback Playlist
- **WHEN** the Host selects a different Fallback Playlist
- **THEN** the Fallback Playlist gate is re-validated against it

### Requirement: History Playlist
When a History Playlist is configured, the system SHALL append each Track to it after the Track has been played. The History Playlist SHALL be optional.

#### Scenario: Appending played Tracks
- **WHEN** a Track finishes playing in a Party with a History Playlist configured
- **THEN** the Track is appended to that playlist at the Music Provider

#### Scenario: No History Playlist
- **WHEN** a Track finishes playing in a Party with no History Playlist configured
- **THEN** no playlist is modified and playback continues normally

#### Scenario: Provider failure
- **WHEN** appending to the History Playlist fails
- **THEN** playback is not interrupted and the failure is recorded in the Party Log

### Requirement: Party Log
The system SHALL maintain a Party Log for each Party recording: Bans and unbans, Requests approved, rejected or removed, settings changes, Player changes, Player connects and disconnects, act-as-Host sessions, and automatic actions taken by Mods. Each entry SHALL record the time, the acting Member or system component, the action and its subject. The Party Log SHALL be visible only to the Host and Moderators and SHALL be append-only.

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
