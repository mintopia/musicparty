## MODIFIED Requirements

### Requirement: Operational dashboards
Horizon and Pulse SHALL be reachable only behind the admin gate. Horizon's metrics snapshot SHALL be taken on a schedule so its graphs have data, and Horizon SHALL run its supervisors in every environment, not only production and local. Telescope SHALL NOT be installed. The development debug toolbar SHALL NOT be installed in the production image.

#### Scenario: Admin opens Horizon
- **WHEN** an admin opens the Horizon dashboard
- **THEN** it is displayed, with throughput and runtime graphs populated

#### Scenario: Non-admin opens Pulse
- **WHEN** a non-admin requests the Pulse dashboard
- **THEN** access is refused

#### Scenario: Telescope absent
- **WHEN** anyone requests the Telescope path
- **THEN** the system returns not found

#### Scenario: Staging environment
- **WHEN** the application runs with an environment name other than production or local
- **THEN** Horizon starts its supervisors and processes every queue

### Requirement: Prometheus metrics
The system SHALL expose metrics in the Prometheus text format at a configurable path. Access SHALL be denied by default: only a scraper presenting the configured bearer token, or connecting from a configured IP range, SHALL be served. The client address used for the IP check SHALL come from the configured trusted proxies only. The metrics SHALL include:
- HTTP requests counted by method and by response status
- uncaught exceptions
- the Horizon queue and supervisor metrics
- dropped Player frames by reason
- the Party count by state
- for each Live or Paused Party: Members, Queue length, total time played, and the ranked top Tracks, top requesters, most upvoted and most downvoted Requests as shown in Live Stats

Party-scoped series SHALL be labelled by party code and SHALL NOT be emitted for Ended Parties. A scrape SHALL read precomputed Live Stats rather than recompute them.

#### Scenario: Unconfigured access
- **WHEN** neither a metrics token nor an allowed IP range is configured and anyone requests the metrics path
- **THEN** access is refused

#### Scenario: Authorised scrape
- **WHEN** a scraper presents the configured bearer token
- **THEN** the metrics are returned

#### Scenario: Wrong token or address
- **WHEN** a client presents a wrong token from an address outside the allowed range
- **THEN** access is refused

#### Scenario: Spoofed forwarding header
- **WHEN** trusted proxies are restricted to the reverse proxy's address and a client connecting directly sends an allowed address in X-Forwarded-For
- **THEN** access is refused

#### Scenario: Request and error counts
- **WHEN** the application serves a successful page, a not-found response and a request that throws an unhandled exception
- **THEN** the request counters for those methods and statuses and the uncaught-exception counter each increase

#### Scenario: Live Stats exported
- **WHEN** a Live Party has played Tracks and received Votes
- **THEN** the metrics show that Party's time played, top Tracks, top requesters, most upvoted and most downvoted Requests with the same values as its Live Stats

#### Scenario: Ended Party not exported
- **WHEN** a Party is Ended
- **THEN** no party-labelled series are emitted for it and it is counted only under the Ended state

### Requirement: Act as Host
An admin SHALL be able to enter an explicit act-as-Host mode for a Party, giving Host powers in that Party. The mode SHALL end automatically after a configured time. Entering and leaving the mode, including automatic expiry, MUST be recorded in that Party's Party Log, the same log the Host and Moderators read, and actions taken in it SHALL be attributable to the admin acting as Host. Admins MUST NOT have Host powers in a Party outside this mode.

#### Scenario: Enter mode
- **WHEN** an admin enters act-as-Host mode for a Party
- **THEN** a Party Log entry records the admin and start time, and the admin can use Host controls

#### Scenario: No implicit powers
- **WHEN** an admin who has not entered act-as-Host mode attempts a Host-only action in a Party
- **THEN** it is refused

#### Scenario: Leave mode
- **WHEN** the admin leaves the mode
- **THEN** a Party Log entry records the end and Host powers are removed

#### Scenario: Mode expires
- **WHEN** the configured time passes without the admin leaving
- **THEN** Host powers are removed and a Party Log entry records the expiry

#### Scenario: Visible to the Host
- **WHEN** the Host opens the Party Log after an admin has acted as Host
- **THEN** the start and end entries are listed and flagged as act-as-Host
