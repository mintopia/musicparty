## MODIFIED Requirements

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

## ADDED Requirements

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
