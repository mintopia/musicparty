## MODIFIED Requirements

### Requirement: Social provider credentials
Admins SHALL be able to configure the social providers Discord, Twitch, Steam, Spotify, Google, Facebook, Laravel Passport and Spotify Search: enable or disable each and set its credentials. Laravel Passport also takes a server URL and a display name. Spotify Search is never offered for login; it supplies the application the Host's Search account links to. Stored secrets MUST be encrypted at rest and MUST NOT be displayed back in full after saving. Only enabled, configured login providers SHALL be offered on the login page.

#### Scenario: Enable provider
- **WHEN** an admin saves valid credentials and enables a provider
- **THEN** the login page offers that provider

#### Scenario: Google and Facebook offered
- **WHEN** an admin opens the provider settings
- **THEN** Google and Facebook are listed alongside the other providers, each with a Client ID and Client Secret

#### Scenario: Laravel Passport
- **WHEN** an admin configures Laravel Passport with a server URL, credentials and the display name "Mintopia ID"
- **THEN** the login page offers "Mintopia ID"

#### Scenario: Spotify Search
- **WHEN** an admin enables Spotify Search with its own client credentials
- **THEN** it is not offered on the login page, and Hosts are asked to link a Search account

#### Scenario: Secret hidden
- **WHEN** an admin reopens the provider settings
- **THEN** the secret is shown masked and is only replaced if a new value is entered

#### Scenario: Disabled provider
- **WHEN** a provider is disabled
- **THEN** it is not offered on the login page and logins through it are refused
