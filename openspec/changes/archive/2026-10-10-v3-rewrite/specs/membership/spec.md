## ADDED Requirements

### Requirement: Open join by party code
Anyone with a Party's party code SHALL be able to join that Party as a Member without an invitation or approval. Joining SHALL require the user to be logged in. A joined user SHALL have exactly one Party Role in that Party, defaulting to Guest.

#### Scenario: Joining with a valid code
- **WHEN** a logged-in user submits a valid party code
- **THEN** the user becomes a Member of the Party with the Guest role

#### Scenario: Unknown code
- **WHEN** a user submits a party code that matches no Party
- **THEN** the system reports that the Party was not found and no membership is created

#### Scenario: Rejoining
- **WHEN** an existing Member submits the party code again
- **THEN** no duplicate membership is created and the Member keeps their existing role

#### Scenario: Joining an Ended Party
- **WHEN** a user submits the code of an Ended Party
- **THEN** the user may view the read-only history but cannot request, vote or rate

### Requirement: Anonymous read-only access
Without logging in, a visitor SHALL be able to view only the TV screen, the public now-playing view and the public playback broadcast of a Party. Anonymous access MUST NOT allow requesting, voting, rating or searching, and MUST NOT expose Member identifiers.

#### Scenario: Anonymous TV screen
- **WHEN** an anonymous visitor opens a Party's TV screen
- **THEN** the current Track, Up Next and Queue are shown without any interactive controls

#### Scenario: Anonymous request attempt
- **WHEN** an anonymous visitor attempts to request or vote
- **THEN** the system requires login and no Request or Vote is created

### Requirement: Login with social credentials
The system SHALL let users log in with the social providers enabled by an instance admin (Discord, Twitch, Steam, Spotify). Logging in SHALL NOT by itself grant any Party Role.

#### Scenario: Login with an enabled provider
- **WHEN** a user completes login with an enabled social provider
- **THEN** the user is logged in and can join Parties

#### Scenario: Disabled provider
- **WHEN** a social provider has not been enabled by an admin
- **THEN** it is not offered on the login page and its login route is refused

### Requirement: Party Roles
Every Member SHALL hold exactly one Party Role: Host, Moderator, VIP or Guest. The Host SHALL control the Party's settings and Player and SHALL be able to do everything a Moderator can.

#### Scenario: Single role
- **WHEN** a user joins a Party
- **THEN** they hold exactly one Party Role in it

### Requirement: Moderator permissions
A Moderator SHALL be able to manage the Queue (approve, reject and remove Requests), manage Bans and view the Party Log, but SHALL NOT access Party settings or the Player.

#### Scenario: Moderator manages the Queue
- **WHEN** a Moderator rejects a Pending Request
- **THEN** the Request is Rejected and the action is recorded in the Party Log

#### Scenario: Moderator denied settings
- **WHEN** a Moderator attempts to change Party settings or control the Player
- **THEN** the system refuses

### Requirement: VIP permissions
A VIP SHALL be exempt from per-Member request limits and otherwise have Guest abilities.

#### Scenario: VIP exempt from limits
- **WHEN** a VIP who has reached the maximum Requests per Member requests another Track
- **THEN** the Request is accepted

### Requirement: Guest permissions
A Guest SHALL be able to search, request, vote and rate, and SHALL NOT moderate.

#### Scenario: Guest denied moderation
- **WHEN** a Guest attempts to remove another Member's Request
- **THEN** the system refuses

### Requirement: Assigning roles
The Host SHALL be able to promote a Member to Moderator or VIP and demote them to Guest. Moderators SHALL NOT change roles. The Host's role SHALL NOT be changed or removed by other Members. Role changes SHALL be recorded in the Party Log.

#### Scenario: Promote to Moderator
- **WHEN** the Host promotes a Guest to Moderator
- **THEN** the Member holds the Moderator role and the change is logged

#### Scenario: Moderator cannot promote
- **WHEN** a Moderator attempts to change another Member's role
- **THEN** the system refuses

### Requirement: Ban status
The Host and Moderators SHALL be able to Ban and unban Members. A Ban is a status distinct from the Party Role: a Banned Member keeps their role but MUST NOT request, vote or rate in that Party. A Moderator SHALL NOT Ban the Host or another Moderator. Bans and unbans SHALL be recorded in the Party Log. A Banned Member MAY still view the Party's public views.

#### Scenario: Banning a Guest
- **WHEN** a Moderator Bans a Guest
- **THEN** the Guest can no longer request, vote or rate and the Ban is logged

#### Scenario: Banned Member attempts to request
- **WHEN** a Banned Member attempts to request a Track
- **THEN** the system refuses

#### Scenario: Unban restores abilities
- **WHEN** a Member is unbanned
- **THEN** they regain the abilities of their Party Role

#### Scenario: Moderator cannot Ban Host
- **WHEN** a Moderator attempts to Ban the Host
- **THEN** the system refuses

### Requirement: Instance roles
The system SHALL support the instance roles admin and create-party. Instance roles are independent of Party Roles.

#### Scenario: Admin without membership
- **WHEN** an admin has not joined a Party
- **THEN** the admin has no Party Role in it and no Party-level powers except through act-as-Host

### Requirement: Admin act-as-Host
An admin SHALL be able to explicitly enter an act-as-Host mode for a Party, granting Host abilities for that Party only. Entering and leaving the mode, and actions taken while in it, SHALL be recorded in the Party Log attributed to the admin. Admin status alone MUST NOT grant Host abilities.

#### Scenario: Entering act-as-Host
- **WHEN** an admin chooses to act as Host of a Party
- **THEN** the admin may change that Party's settings and control its Player, and the Party Log records the session start

#### Scenario: Actions attributed
- **WHEN** an admin acting as Host changes a setting
- **THEN** the Party Log entry is attributed to the admin and flagged as made in act-as-Host mode

#### Scenario: No implicit powers
- **WHEN** an admin who has not entered act-as-Host mode attempts a Host-only action
- **THEN** the system refuses

#### Scenario: Suspended user
- **WHEN** an admin suspends a user
- **THEN** the user can no longer log in or act in any Party
