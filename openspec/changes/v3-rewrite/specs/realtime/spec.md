## ADDED Requirements

### Requirement: Public party channel
The system SHALL broadcast a Party's now-playing state, Up Next, and Queue on a public channel keyed by the party code, readable by anonymous clients. Payloads SHALL include Track information, scores, requester nicknames and Decorations, and SHALL NOT include member identifiers, tokens or other secrets.

#### Scenario: Queue change broadcast
- **WHEN** a Request is added, voted on, removed or promoted
- **THEN** an update with the new Queue state is broadcast on the public channel

#### Scenario: Anonymous subscriber
- **WHEN** an unauthenticated client subscribes to a Party's public channel
- **THEN** it receives now-playing, Up Next and Queue updates and nothing more

#### Scenario: Nothing secret in public payloads
- **WHEN** any event on a public channel is inspected
- **THEN** it contains no member IDs, email addresses, Music Provider tokens, Player Tokens or Pending Requests

#### Scenario: Pending Requests hidden
- **WHEN** a Request is Pending
- **THEN** it does not appear on the public channel until approved

### Requirement: Presence channel
The system SHALL provide a presence channel per Party that only the Party's Members can join and that reports who is currently online, using the Member's nickname and avatar only.

#### Scenario: Member joins
- **WHEN** a Member joins the presence channel
- **THEN** other Members see them come online

#### Scenario: Non-member
- **WHEN** a user who is not a Member, or a Banned Member, tries to join
- **THEN** authorisation is refused

### Requirement: Per-member channel
The system SHALL provide a private channel per Member, authorisable only by that Member, carrying their own Votes, their own Pending Requests and notifications such as rejections and Bans.

#### Scenario: Own Votes
- **WHEN** a Member votes
- **THEN** their Vote state is sent on their own channel

#### Scenario: Another Member's channel
- **WHEN** a user tries to subscribe to another Member's channel
- **THEN** authorisation is refused

#### Scenario: Rejection notification
- **WHEN** a Moderator rejects a Member's Request
- **THEN** that Member is notified on their channel with the reason

### Requirement: Moderator channel
The system SHALL provide a private channel per Party, authorisable only by the Host and Moderators, carrying Pending Requests and Party Log entries.

#### Scenario: Pending Request arrives
- **WHEN** a Request is held as Pending
- **THEN** Moderators and the Host receive it on the Moderator channel

#### Scenario: Guest subscribes
- **WHEN** a Guest or VIP tries to subscribe to the Moderator channel
- **THEN** authorisation is refused

#### Scenario: Role removed
- **WHEN** a Moderator's role is removed
- **THEN** they stop receiving Moderator channel events

### Requirement: Player channel
The system SHALL provide a private channel per Player, authorisable only with a valid, unrevoked Player Token for that Party, carrying Player frames inbound and commands outbound.

#### Scenario: Authorised with Player Token
- **WHEN** a client authorises with a Player Token for the Party
- **THEN** it may subscribe and send Player frames

#### Scenario: Wrong Party
- **WHEN** a Player Token for another Party is used
- **THEN** authorisation is refused

#### Scenario: Member session cannot join
- **WHEN** a logged-in Member or admin session tries to subscribe without a Player Token
- **THEN** authorisation is refused

### Requirement: Channel authorisation
Every non-public channel SHALL deny by default, and authorisation SHALL be re-evaluated on every subscription. No channel SHALL authorise all users.

#### Scenario: Unauthenticated private subscribe
- **WHEN** an unauthenticated client subscribes to a private or presence channel
- **THEN** authorisation is refused

#### Scenario: Unknown party code
- **WHEN** a client subscribes to a channel for a party code that does not exist
- **THEN** authorisation is refused with no information on whether the Party exists

### Requirement: Event payloads are consistent with the API
Realtime payloads SHALL use the same shapes as the corresponding API resources so a client can apply an event or a fetched resource interchangeably, and SHALL carry a version and sequence so clients can detect missed events.

#### Scenario: Missed event
- **WHEN** a client sees a sequence gap
- **THEN** it can fetch the current state from the API to recover

### Requirement: AsyncAPI documentation
Every channel and broadcast event SHALL be described in a committed AsyncAPI document, including channel name, authorisation rule, event name and payload schema. CI SHALL fail if any broadcast event or channel is undocumented or if the committed document differs from what the code produces.

#### Scenario: Undocumented event
- **WHEN** a change adds a broadcast event without documenting it
- **THEN** the CI check fails naming the event

#### Scenario: Documentation drift
- **WHEN** a payload shape changes without the AsyncAPI document being regenerated
- **THEN** the CI check fails

#### Scenario: Fully documented
- **WHEN** every channel and event is documented and the document is current
- **THEN** the CI check passes
