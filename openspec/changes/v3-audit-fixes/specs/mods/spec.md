## MODIFIED Requirements

### Requirement: Scheduled actions extension point
A Mod SHALL be able to declare Scheduled actions that run while the Party is Live. A Scheduled action MAY create system Requests, which have no requester and pass through the Party's rules unless the Mod states otherwise. Scheduled actions MUST NOT run for Paused or Ended Parties. Each Scheduled action SHALL run at most once per interval per Party, even when scheduler runs overlap.

#### Scenario: Action creates a system Request
- **WHEN** a Scheduled action fires for a Live Party
- **THEN** a system Request is created in the Queue and attributed in the Party Log to the Mod

#### Scenario: Paused Party
- **WHEN** a Party is Paused
- **THEN** Scheduled actions do not run

#### Scenario: Overlapping runs
- **WHEN** two scheduler runs reach the same due Scheduled action at the same moment
- **THEN** the action runs once and creates at most one system Request
