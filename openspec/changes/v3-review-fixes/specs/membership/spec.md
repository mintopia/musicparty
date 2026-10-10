## MODIFIED Requirements

### Requirement: Login with social credentials
The system SHALL let users log in with the social providers enabled by an instance admin (Discord, Twitch, Steam, Spotify). Logging in SHALL NOT by itself grant any Party Role. One social identity (provider and external account id) SHALL map to exactly one user, even when the login callback is submitted twice at once. Logging out SHALL require a POST request with CSRF protection.

#### Scenario: Login with an enabled provider
- **WHEN** a user completes login with an enabled social provider
- **THEN** the user is logged in and can join Parties

#### Scenario: Disabled provider
- **WHEN** a social provider has not been enabled by an admin
- **THEN** it is not offered on the login page and its login route is refused

#### Scenario: Double-submitted callback
- **WHEN** the same provider callback for a new identity is processed twice concurrently
- **THEN** one user and one linked account exist afterwards, and both requests log in as that user

#### Scenario: Logout by link
- **WHEN** a page embeds a GET request to the logout path
- **THEN** the user stays logged in

### Requirement: Anonymous read-only access
Without logging in, a visitor SHALL be able to view only the TV screen, the public now-playing view and the public playback broadcast of a Party. These surfaces are public by design. Anonymous access MUST NOT allow requesting, voting, rating or searching, and MUST NOT expose Member identifiers. Anonymous web routes addressed by party code SHALL be rate limited per client address.

#### Scenario: Anonymous TV screen
- **WHEN** an anonymous visitor opens a Party's TV screen
- **THEN** the current Track, Up Next and Queue are shown without any interactive controls

#### Scenario: Anonymous request attempt
- **WHEN** an anonymous visitor attempts to request or vote
- **THEN** the system requires login and no Request or Vote is created

#### Scenario: Code guessing
- **WHEN** one address requests TV screens for more party codes than the configured limit allows in a minute
- **THEN** further requests are refused with 429

## ADDED Requirements

### Requirement: Signup completion
A new user SHALL complete signup (choose a nickname, and accept the terms when the instance has a terms of service URL) before creating or joining a Party, requesting, voting or using the API with their session. When no terms of service URL is configured, the terms acceptance time SHALL be set when the account is created and no terms step SHALL be shown.

#### Scenario: Signup skipped
- **WHEN** a user who has not completed signup opens the create-party or join page, or calls the API with their session
- **THEN** they are redirected to signup, or the API returns 403 with a signup-required error code

#### Scenario: Terms configured
- **WHEN** an admin has set a terms of service URL and a new user signs up
- **THEN** the user must accept the terms before continuing, and the acceptance time is stored

#### Scenario: No terms configured
- **WHEN** no terms of service URL is set and a new user logs in for the first time
- **THEN** the terms acceptance time is set at account creation and signup asks only for a nickname

#### Scenario: Signup and logout reachable
- **WHEN** a user who has not completed signup opens the signup page or logs out
- **THEN** the request is allowed
