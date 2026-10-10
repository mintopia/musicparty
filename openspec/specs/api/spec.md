# api Specification

## Purpose
Synced from the archived v3-rewrite change.

## Requirements

### Requirement: Versioned API under /api/v1
The system SHALL expose every capability available in the UI through a JSON API under the /api/v1 prefix. Breaking changes SHALL only ship under a new version prefix.

#### Scenario: Capability parity
- **WHEN** a Member can perform an action in the UI, such as searching, requesting, voting, rating or moderating
- **THEN** an equivalent /api/v1 endpoint exists with the same authorisation rules

#### Scenario: Unknown version
- **WHEN** a client calls a path under an unsupported version
- **THEN** the system returns 404 in the standard error format

### Requirement: OpenAPI specification
The system SHALL generate an OpenAPI document code-first from its routes, request validation and API resources using a maintained generator, SHALL commit it to the repository, and SHALL serve it. Every /api route and every API resource SHALL be documented, including which credentials and abilities it accepts. An undocumented endpoint or resource SHALL be treated as a defect. CI SHALL fail when the committed document differs from the generated one, when any /api route is absent from it, or when the document is invalid.

#### Scenario: Undocumented route
- **WHEN** a change adds a /api route that is not in the OpenAPI document
- **THEN** the CI check fails naming the route

#### Scenario: Spec drift
- **WHEN** a request rule or resource changes and the committed document is not regenerated
- **THEN** the CI check fails

#### Scenario: Auth documented
- **WHEN** a route requires authentication
- **THEN** the document states which credentials are accepted and which token abilities are required

#### Scenario: Documentation access in production
- **WHEN** a non-admin requests the interactive API documentation in production
- **THEN** access is refused, while the committed document remains in the repository

### Requirement: Authentication
The API SHALL accept exactly three credential types: the Sanctum session cookie for the UI, Player Tokens for Players, and Integration Tokens for trusted integrations. Player Tokens and Integration Tokens SHALL both be Sanctum tokens with abilities, checked by one guard. The API SHALL NOT offer self-service user tokens. A session request SHALL be exempt from CSRF protection only when it is authenticated by a valid token instead of the session.

#### Scenario: Session cookie
- **WHEN** a logged-in UI session calls the API
- **THEN** it is authenticated as that user with CSRF protection

#### Scenario: Bogus bearer header
- **WHEN** a request carries a session cookie and an Authorization header with an invalid token
- **THEN** CSRF protection still applies to it

#### Scenario: Player Token abilities
- **WHEN** a Player Token calls an endpoint outside the Player abilities or for another Party
- **THEN** the system returns 403

#### Scenario: Unauthenticated
- **WHEN** a call to a protected endpoint has no valid credential
- **THEN** the system returns 401 in the standard error format

#### Scenario: No self-service tokens
- **WHEN** an ordinary user looks for a way to create an API token
- **THEN** none is offered and the endpoint does not exist

#### Scenario: Expired token
- **WHEN** a Player Token or Integration Token past its expiry is used
- **THEN** the system returns 401

### Requirement: Integration Tokens
Only instance admins SHALL be able to issue, list and revoke Integration Tokens, each with a name, explicit abilities and an optional expiry. A token SHALL be shown in full only at creation. The last-used time SHALL be recorded without writing to the database on every request.

#### Scenario: Admin issues token
- **WHEN** an admin creates an Integration Token with chosen abilities
- **THEN** the full token is shown once and the creation is recorded

#### Scenario: Non-admin attempts
- **WHEN** a non-admin tries to issue an Integration Token
- **THEN** the system returns 403

#### Scenario: Ability enforcement
- **WHEN** an Integration Token calls an endpoint outside its abilities
- **THEN** the system returns 403

#### Scenario: Revoked token
- **WHEN** a revoked Integration Token is used
- **THEN** the system returns 401

#### Scenario: Last used
- **WHEN** an Integration Token makes 100 calls within one minute
- **THEN** its last-used time is updated at most once in that minute

### Requirement: Party Export endpoint
The API SHALL provide an endpoint returning a Party Export for an Ended Party as a versioned JSON dataset, documented in the OpenAPI document with a schema version field. It SHALL be available to the Host, admins and Integration Tokens with the export ability, and SHALL NOT include email addresses or provider tokens.

#### Scenario: Export of an Ended Party
- **WHEN** an authorised caller requests the export of an Ended Party
- **THEN** it receives the full dataset including its schema version

#### Scenario: Party not Ended
- **WHEN** the Party is Live or Paused
- **THEN** the system returns 409 in the standard error format

#### Scenario: Unauthorised
- **WHEN** a caller who is neither Host, admin nor holding the export ability asks
- **THEN** the system returns 403

#### Scenario: Reopened Party
- **WHEN** a Host reopens an Ended Party
- **THEN** the export endpoint returns 409 until it is Ended again

### Requirement: Error format
All API errors SHALL use one JSON shape with a machine-readable code, a human-readable message, and, for validation errors, per-field messages, with the correct HTTP status code. Errors SHALL NOT leak stack traces or internal details.

#### Scenario: Validation failure
- **WHEN** a request fails validation
- **THEN** the system returns 422 with per-field messages in the standard format

#### Scenario: Not found vs forbidden
- **WHEN** a caller asks for a resource they may not know exists
- **THEN** the system returns 404 rather than 403

#### Scenario: Server error
- **WHEN** an unexpected error occurs
- **THEN** the system returns 500 in the standard format with no internal details

### Requirement: Rate limits
The API SHALL rate limit by credential, with stricter limits for search, Request creation, voting, joining and the Webhook, and SHALL return 429 with a Retry-After header when exceeded. Limits for joining, Request creation and voting SHALL be per user and SHALL apply equally to the web UI and the API. Search SHALL be limited by a generous leaky bucket per user that is global across all Parties and shared by the web UI and the API, allowing a burst and then a steady rate. Unauthenticated routes addressed by party code, such as the TV screen, SHALL be limited per client address. Limits SHALL be documented and configurable.

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

#### Scenario: Party code enumeration
- **WHEN** one client address requests TV screens for many different party codes in a minute
- **THEN** requests above the configured limit are refused with 429, and a TV screen that is already open keeps working

### Requirement: Pagination and consistency
List endpoints SHALL be paginated with a documented default and maximum, and API resources SHALL match the payloads used by realtime events.

#### Scenario: Page size bound
- **WHEN** a client requests more than the maximum page size
- **THEN** the system clamps it to the maximum or returns 422
