# realtime Specification

## Purpose
Synced from the archived v3-rewrite change.

## Requirements

### Requirement: Public party channel
The system SHALL broadcast a Party's now-playing state, Up Next, and Queue on the public channel `party.{code}`, readable by anonymous clients. Payloads SHALL include Track information, scores, requester nicknames and Decorations, and SHALL NOT include member identifiers, tokens or other secrets.

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
The system SHALL provide a presence channel `party.{code}.members` per Party that only the Party's Members can join and that reports who is currently online, using the Member's nickname and avatar only.

#### Scenario: Member joins
- **WHEN** a Member joins the presence channel
- **THEN** other Members see them come online

#### Scenario: Non-member
- **WHEN** a user who is not a Member, or a Banned Member, tries to join
- **THEN** authorisation is refused

### Requirement: Per-member channel
The system SHALL provide a private channel `party.{code}.member.{memberId}` per Member, authorisable only by that Member, carrying their own Votes, their own Pending Requests and notifications such as rejections and Bans.

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
The system SHALL provide a private channel `party.{code}.moderators` per Party, authorisable only by the Host and Moderators, carrying Pending Requests and Party Log entries.

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
The system SHALL provide a private channel `player.{code}` per Player, authorisable only with a valid, unrevoked Player Token for that Party, carrying Player frames inbound and commands outbound.

#### Scenario: Authorised with Player Token
- **WHEN** a client authorises with a Player Token for the Party
- **THEN** it may subscribe and send Player frames

#### Scenario: Wrong Party
- **WHEN** a Player Token for another Party is used
- **THEN** authorisation is refused

#### Scenario: Member session cannot join
- **WHEN** a logged-in Member or admin session tries to subscribe without a Player Token
- **THEN** authorisation is refused

### Requirement: Browser Player channel
The system SHALL provide a private channel `party.{code}.browser-player` per Party, authorisable only by users who can manage the Party, carrying commands to the Browser Player.

#### Scenario: Non-manager subscribes
- **WHEN** a user who cannot manage the Party tries to subscribe to the Browser Player channel
- **THEN** authorisation is refused

### Requirement: Explicit event wire names
Every broadcast event SHALL declare an explicit dotted wire name through `broadcastAs()` (for example `queue.updated`, `theme.updated`, `stats.updated`, `pending_request.added`, `request.rejected`, `player.command`). Clients SHALL listen using the wire name with a leading dot, and the AsyncAPI document SHALL list the same name for each message.

#### Scenario: Event without a wire name
- **WHEN** a class implementing `ShouldBroadcast` or `ShouldBroadcastNow` does not declare `broadcastAs()`
- **THEN** the architecture test fails

### Requirement: Channel authorisation
Every non-public channel SHALL deny by default, and authorisation SHALL be re-evaluated on every subscription. No channel SHALL authorise all users.

#### Scenario: Unauthenticated private subscribe
- **WHEN** an unauthenticated client subscribes to a private or presence channel
- **THEN** authorisation is refused

#### Scenario: Unknown party code
- **WHEN** a client subscribes to a channel for a party code that does not exist
- **THEN** authorisation is refused with no information on whether the Party exists

### Requirement: Event payloads are consistent with the API
Realtime payloads SHALL use the same shapes as the corresponding API resources so a client can apply an event or a fetched resource interchangeably, and SHALL carry a payload version. Clients SHALL recover missed events by fetching current state after reconnecting and when they become visible again, rather than by tracking sequence numbers.

#### Scenario: Reconnect
- **WHEN** a client's realtime connection drops and reconnects
- **THEN** the client fetches the current state so that events missed during the gap are reflected

#### Scenario: Device wakes
- **WHEN** a phone or TV page becomes visible again after being hidden
- **THEN** the client fetches the current state

#### Scenario: Long-running Party
- **WHEN** a Party runs for more than 24 hours or the cache restarts
- **THEN** the TV screen keeps applying Queue events without a manual reload

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

### Requirement: Runtime client connection settings
The address, port, scheme and app key that browsers use to connect to the realtime server SHALL come from server configuration at page render time. They MUST NOT be compiled into the frontend bundle. These public settings SHALL be configured separately from the internal address the application uses to publish events, and SHALL default to the application's public URL. Without an app key, pages SHALL still load and work without realtime updates.

#### Scenario: Public address differs from internal address
- **WHEN** the application publishes to the realtime server on an internal network address and the public address is configured separately
- **THEN** pages tell browsers to connect to the public address, never the internal one

#### Scenario: Defaults from the public URL
- **WHEN** no public realtime address is configured
- **THEN** browsers are told to connect to the host, port and scheme of the application's public URL

#### Scenario: Same image, different operators
- **WHEN** two operators run the same published image with different public addresses
- **THEN** each operator's browsers connect to that operator's address without a rebuild

#### Scenario: No app key
- **WHEN** a page is rendered without a realtime app key configured
- **THEN** the page loads and works, without live updates

### Requirement: Paced client refreshes
Clients that refetch state in response to an event SHALL debounce and randomly jitter the refetch, and SHALL NOT let a refetch cancel an action the user has in flight.

#### Scenario: Burst of Queue events
- **WHEN** ten Queue events arrive at a phone within two seconds
- **THEN** the phone refetches once, after a randomised short delay

#### Scenario: Vote in flight
- **WHEN** a Queue event arrives while the Member's Vote is being submitted
- **THEN** the Vote completes and its result or error is shown
