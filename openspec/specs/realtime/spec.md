# realtime Specification

## Purpose
Synced from the archived v3-rewrite change.

## Requirements

### Requirement: Public party channel
The system SHALL broadcast a Party's state (Live, Paused or Ended), now-playing state, Up Next, and Queue on a public channel keyed by the party code, readable by anonymous clients. Payloads SHALL include Track information, Scores, requester nicknames and Decorations, and SHALL NOT include member identifiers, tokens or other secrets. Broadcasts SHALL be sent only after the change they describe has been committed.

#### Scenario: Queue change broadcast
- **WHEN** a Request is added, voted on, removed or promoted
- **THEN** an update with the new Queue state is broadcast on the public channel

#### Scenario: Party state change broadcast
- **WHEN** the Host pauses, resumes or ends a Party
- **THEN** the new Party state is broadcast on the public channel and open phone and TV pages show it without a reload

#### Scenario: Anonymous subscriber
- **WHEN** an unauthenticated client subscribes to a Party's public channel
- **THEN** it receives Party state, now-playing, Up Next and Queue updates and nothing more

#### Scenario: Nothing secret in public payloads
- **WHEN** any event on a public channel is inspected
- **THEN** it contains no member IDs, email addresses, Music Provider tokens, Player Tokens or Pending Requests

#### Scenario: Pending Requests hidden
- **WHEN** a Request is Pending
- **THEN** it does not appear on the public channel until approved

#### Scenario: Rolled-back change
- **WHEN** a transaction that would have changed the Queue or written a Party Log entry is rolled back
- **THEN** no broadcast is sent for it

### Requirement: Presence channel
The system SHALL provide a presence channel `party.{code}.members` per Party that only the Party's Members can join and that reports who is currently online, using the Member's nickname and avatar only.

#### Scenario: Member joins
- **WHEN** a Member joins the presence channel
- **THEN** other Members see them come online

#### Scenario: Non-member
- **WHEN** a user who is not a Member, or a Banned Member, tries to join
- **THEN** authorisation is refused

### Requirement: Per-member channel
The system SHALL provide a private channel per Member, authorisable only by that Member, carrying their own Votes and Ratings, their own Pending Requests and their outcome, and notifications such as rejections and Bans.

#### Scenario: Own Votes
- **WHEN** a Member votes from one device
- **THEN** their Vote state is sent on their own channel and their other open pages show it

#### Scenario: Own Rating
- **WHEN** a Member rates the playing Track
- **THEN** their Rating is sent on their own channel

#### Scenario: Another Member's channel
- **WHEN** a user tries to subscribe to another Member's channel
- **THEN** authorisation is refused

#### Scenario: Rejection notification
- **WHEN** a Moderator rejects a Member's Request
- **THEN** that Member is notified on their channel with the reason and their open Party page shows it

#### Scenario: Pending Request approved
- **WHEN** a Moderator approves a Member's Pending Request
- **THEN** that Member is notified on their channel and their open Party page shows it

### Requirement: Moderator channel
The system SHALL provide a private channel per Party, authorisable only by the Host and Moderators, carrying Pending Requests and Party Log entries. The Pending Requests and Party Log pages SHALL apply these events as they arrive.

#### Scenario: Pending Request arrives
- **WHEN** a Request is held as Pending
- **THEN** Moderators and the Host receive it on the Moderator channel and it appears on an open Pending Requests page without a reload

#### Scenario: Party Log entry arrives
- **WHEN** a Party Log entry is written
- **THEN** it appears on an open Party Log page without a reload

#### Scenario: Guest subscribes
- **WHEN** a Guest or VIP tries to subscribe to the Moderator channel
- **THEN** authorisation is refused

#### Scenario: Role removed
- **WHEN** a Moderator's role is removed while they are connected
- **THEN** their realtime connection is closed and on reconnecting they cannot subscribe to the Moderator channel

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
Every non-public channel SHALL deny by default, and authorisation SHALL be re-evaluated on every subscription. No channel SHALL authorise all users. When a user is Banned, loses a Party Role or is suspended, or a Player Token is revoked, the affected live connections SHALL be closed so that authorisation is evaluated again against current state.

#### Scenario: Unauthenticated private subscribe
- **WHEN** an unauthenticated client subscribes to a private or presence channel
- **THEN** authorisation is refused

#### Scenario: Unknown party code
- **WHEN** a client subscribes to a channel for a party code that does not exist
- **THEN** authorisation is refused with no information on whether the Party exists

#### Scenario: Banned while connected
- **WHEN** a connected Member is Banned
- **THEN** their realtime connections are closed, and on reconnecting their member and presence channel subscriptions are refused

#### Scenario: Suspended while connected
- **WHEN** an admin suspends a user who is connected to a Party
- **THEN** their realtime connections are closed

### Requirement: Event payloads are consistent with the API
Realtime payloads and API resources for the same thing SHALL be produced by the same serializer, so a client can apply an event or a fetched resource interchangeably, and SHALL carry a payload version. Public payloads SHALL carry only public state; per-Member state SHALL come from the API for that Member and from the per-member channel. Clients SHALL apply event payloads directly and SHALL NOT refetch state in response to an ordinary event. Clients SHALL fetch current state only on first load, after reconnecting, when they become visible again, or when a payload version is newer than they understand.

#### Scenario: Queue event applied
- **WHEN** a phone page receives a Queue event
- **THEN** it shows the new Queue from the payload, keeps the Member's own Votes, and makes no HTTP request

#### Scenario: Same shape
- **WHEN** a Queue entry from a broadcast is compared with the same entry from the API
- **THEN** every public field has the same name, type and value

#### Scenario: Reconnect
- **WHEN** a client's realtime connection drops and reconnects
- **THEN** the client fetches the current state so that events missed during the gap are reflected

#### Scenario: Device wakes
- **WHEN** a phone or TV page becomes visible again after being hidden
- **THEN** the client fetches the current state

#### Scenario: Long-running Party
- **WHEN** a Party runs for more than 24 hours or the cache restarts
- **THEN** the TV screen keeps applying Queue events without a manual reload

#### Scenario: 500 Members watching
- **WHEN** a Queue change is broadcast to a Party with 500 connected Members
- **THEN** the server handles no follow-up HTTP request because of it

### Requirement: AsyncAPI documentation
Every channel and broadcast event SHALL be described in a committed AsyncAPI document, including channel name, authorisation rule, explicit event name and payload schema. Every broadcast event SHALL declare its wire name explicitly rather than derive it from its class. CI SHALL fail if any broadcast event or channel is undocumented, if a documented payload schema does not match what the serializer produces, or if the document is invalid.

#### Scenario: Undocumented event
- **WHEN** a change adds a broadcast event without documenting it
- **THEN** the CI check fails naming the event

#### Scenario: Undocumented channel
- **WHEN** a change registers a channel without documenting it
- **THEN** the CI check fails naming the channel

#### Scenario: Documentation drift
- **WHEN** a payload shape changes without the AsyncAPI document being updated
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

### Requirement: Configurable allowed origins
The realtime server SHALL accept connections from the origins listed in configuration, and SHALL accept any origin when none are configured, because Players and third-party clients connect from origins other than the application's.

#### Scenario: Default
- **WHEN** no allowed origins are configured
- **THEN** a client from any origin can connect, and channel authorisation still applies

#### Scenario: Restricted
- **WHEN** an Operator configures a list of allowed origins
- **THEN** a browser connection from an unlisted origin is refused

### Requirement: Connection capacity
The realtime server SHALL NOT be limited to about 1,000 concurrent connections by the process's open-file limit or by its event loop. The image SHALL include an event-loop extension that scales beyond `stream_select`, and the shipped Compose files SHALL raise the open-file limit for the realtime server.

#### Scenario: More than 1,000 guests
- **WHEN** 2,000 clients connect to one realtime server
- **THEN** every connection is accepted and receives broadcasts
