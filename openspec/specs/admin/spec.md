# admin Specification

## Purpose
Synced from the archived v3-rewrite change.

## Requirements

### Requirement: Admin gate
All administration features SHALL be available only to users holding the instance admin role. Everyone else, including Hosts, MUST be refused.

#### Scenario: Non-admin refused
- **WHEN** a user without the admin role requests an admin page or admin API endpoint
- **THEN** access is refused

#### Scenario: Anonymous refused
- **WHEN** an unauthenticated visitor requests an admin page
- **THEN** they are redirected to log in and not shown admin content

### Requirement: User management
Admins SHALL be able to list and search users, suspend and unsuspend a user, and grant or revoke the instance roles admin and create-party. A suspended user MUST be unable to log in or use an existing session or token, and SHALL be removed from realtime access. Admins MUST NOT be able to remove the last remaining admin role, nor suspend themselves.

#### Scenario: Suspend user
- **WHEN** an admin suspends a user
- **THEN** the user's active sessions end and further logins are refused with a message that the account is suspended

#### Scenario: Unsuspend
- **WHEN** an admin unsuspends a user
- **THEN** the user can log in again

#### Scenario: Grant create-party
- **WHEN** an admin grants the create-party role
- **THEN** the user can create Parties

#### Scenario: Last admin protected
- **WHEN** an admin tries to revoke the admin role from the only remaining admin
- **THEN** the change is refused

#### Scenario: Role changes recorded
- **WHEN** an admin changes a user's roles or suspension
- **THEN** the change is recorded with the acting admin and time

### Requirement: Act as Host
An admin SHALL be able to enter an explicit act-as-Host mode for a Party, giving Host powers in that Party. Entering and leaving the mode MUST be recorded in that Party's Party Log, and actions taken in it SHALL be attributable to the admin acting as Host. Admins MUST NOT have Host powers in a Party outside this mode.

#### Scenario: Enter mode
- **WHEN** an admin enters act-as-Host mode for a Party
- **THEN** a Party Log entry records the admin and start time, and the admin can use Host controls

#### Scenario: No implicit powers
- **WHEN** an admin who has not entered act-as-Host mode attempts a Host-only action in a Party
- **THEN** it is refused

#### Scenario: Leave mode
- **WHEN** the admin leaves the mode
- **THEN** a Party Log entry records the end and Host powers are removed

### Requirement: Social provider credentials
Admins SHALL be able to configure login for the social providers Discord, Twitch, Steam and Spotify: enable or disable each and set its credentials. Stored secrets MUST be encrypted at rest and MUST NOT be displayed back in full after saving. Only enabled, configured providers SHALL be offered on the login page.

#### Scenario: Enable provider
- **WHEN** an admin saves valid credentials and enables a provider
- **THEN** the login page offers that provider

#### Scenario: Secret hidden
- **WHEN** an admin reopens the provider settings
- **THEN** the secret is shown masked and is only replaced if a new value is entered

#### Scenario: Disabled provider
- **WHEN** a provider is disabled
- **THEN** it is not offered on the login page and logins through it are refused

### Requirement: Site settings
Admins SHALL be able to edit site settings: site name, logos, favicon, terms of service URL, privacy policy URL and the default Party. URLs MUST be validated, and uploads validated for type and size.

#### Scenario: Update site name
- **WHEN** an admin changes the site name
- **THEN** pages and titles show the new name

#### Scenario: Default Party
- **WHEN** an admin sets a default Party
- **THEN** visitors who arrive without a party code are directed to it

#### Scenario: Invalid URL
- **WHEN** an admin saves a malformed terms of service URL
- **THEN** it is rejected with a validation error

### Requirement: Instance Theme editing
Admins SHALL be able to edit the Instance Theme through a token editor with a live preview and contrast warnings, and reset it to defaults. The editor MUST follow the constraints of the theming capability, including no raw CSS.

#### Scenario: Edit and preview
- **WHEN** an admin changes a token
- **THEN** a preview reflects it before saving, with a contrast warning if applicable

#### Scenario: Reset
- **WHEN** an admin resets the Instance Theme
- **THEN** all tokens return to the v2-matching defaults

### Requirement: Integration Tokens
Admins SHALL be able to issue, list and revoke Integration Tokens, each with a name and a scope of abilities. The token value MUST be shown only once at creation and stored only as a hash. Non-admins MUST NOT be able to create tokens, and there are no self-service user tokens. A revoked token MUST stop working immediately.

#### Scenario: Issue token
- **WHEN** an admin creates an Integration Token
- **THEN** its value is displayed once and subsequently only its name, abilities and last-used time are visible

#### Scenario: Revoke token
- **WHEN** an admin revokes a token
- **THEN** further API calls with it are refused

#### Scenario: Non-admin creation
- **WHEN** a non-admin attempts to create a token
- **THEN** it is refused

### Requirement: Mods catalogue
Admins SHALL see a catalogue of all registered Mods with name, description and whether each is available on the instance. Admins SHALL be able to make a Mod unavailable instance-wide, in which case it MUST NOT be enableable on any Party and any existing enablement SHALL be inactive.

#### Scenario: Browse
- **WHEN** an admin opens the Mods catalogue
- **THEN** every registered Mod is listed with its availability

#### Scenario: Disable instance-wide
- **WHEN** an admin makes a Mod unavailable
- **THEN** Parties that had it enabled no longer run it and Hosts cannot enable it

### Requirement: Operational dashboards
Horizon, Pulse and Telescope SHALL be reachable only behind the admin gate. Telescope SHALL be off unless enabled by configuration.

#### Scenario: Admin opens Horizon
- **WHEN** an admin opens the Horizon dashboard
- **THEN** it is displayed

#### Scenario: Non-admin opens Pulse
- **WHEN** a non-admin requests the Pulse dashboard
- **THEN** access is refused

### Requirement: Admin auditability
Admin actions that change users, roles, credentials, settings, themes, tokens or Mod availability SHALL be recorded with the acting admin and timestamp, with secrets excluded.

#### Scenario: Credential change logged
- **WHEN** an admin changes a social provider secret
- **THEN** an audit record notes who and when, without the secret value
