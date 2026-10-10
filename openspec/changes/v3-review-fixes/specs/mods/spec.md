## MODIFIED Requirements

### Requirement: Decorations
A Mod SHALL be able to attach Decorations to Requests and Plays. A Decoration is structured data: a badge, label, icon, accent token and style variant from an allow-list. Decorations SHALL be carried in API responses and realtime payloads and rendered by core components. Raw HTML, CSS and script MUST NOT be accepted in a Decoration; values outside the allow-list MUST be rejected. A failing decoration provider SHALL be skipped, and its failure SHALL be recorded in the Party Log at most once per Mod per Party per minute, however many Requests or reads hit it. Reading the Queue SHALL NOT otherwise write to the database.

#### Scenario: Decoration displayed
- **WHEN** a Mod attaches a badge Decoration to a Request
- **THEN** the Queue shows the badge for all viewers of the Party and the Decoration is in the public Party payload

#### Scenario: Disallowed value
- **WHEN** a Mod produces a Decoration with an accent token or variant not in the allow-list
- **THEN** it is discarded and not shown

#### Scenario: Disabled Mod
- **WHEN** a Mod is disabled
- **THEN** its Decorations disappear from the Party

#### Scenario: Failing provider
- **WHEN** a Mod's decoration provider throws while 30 Requests are queued and 20 Members load the Queue within a minute
- **THEN** the Queue renders without that Mod's Decorations and the Party Log gains one entry for the failure
