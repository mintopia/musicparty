## MODIFIED Requirements

### Requirement: Rate limits
The API SHALL rate limit by credential, with stricter limits for search, Request creation, voting, joining and the Webhook, and SHALL return 429 with a Retry-After header when exceeded. Limits for joining, Request creation and voting SHALL be per user and SHALL apply equally to the web UI and the API. Search SHALL be limited by a generous leaky bucket per user that is global across all Parties and shared by the web UI and the API, allowing a burst and then a steady rate. Limits SHALL be documented and configurable.

#### Scenario: Limit exceeded
- **WHEN** a client exceeds a limit
- **THEN** the system returns 429 with Retry-After

#### Scenario: Independent buckets
- **WHEN** one Member is limited
- **THEN** other Members are not affected

#### Scenario: Search burst then steady rate
- **WHEN** a user performs the configured burst of searches at once and then keeps searching
- **THEN** the burst succeeds, further searches succeed at the configured steady rate, and searches above it are refused with Retry-After

#### Scenario: Search limit is global per user
- **WHEN** a user searches in two Parties and through both the web UI and the API
- **THEN** all of those searches draw on the same allowance

#### Scenario: Web search limited
- **WHEN** a user over the search limit searches from the Party page
- **THEN** the page still renders, and the search results area says when searching will be available again
