## MODIFIED Requirements

### Requirement: Open join by party code
Anyone with a Party's party code SHALL be able to join that Party as a Member without an invitation or approval. Joining SHALL require the user to be logged in and to have completed signup. A logged-in user who is not a Member SHALL be joined automatically when they open the Party's page by its link or QR code, or are sent to the instance's default Party. Entering the code in the join form SHALL also join. A joined user SHALL have exactly one Party Role in that Party, defaulting to Guest. Join attempts, automatic or not, SHALL be rate limited per user.

#### Scenario: Joining with a valid code
- **WHEN** a logged-in user submits a valid party code
- **THEN** the user becomes a Member of the Party with the Guest role

#### Scenario: Unknown code
- **WHEN** a user submits a party code that matches no Party
- **THEN** the system reports that the Party was not found and no membership is created

#### Scenario: Rejoining
- **WHEN** an existing Member submits the party code again or opens the Party's link
- **THEN** no duplicate membership is created and the Member keeps their existing role

#### Scenario: Joining an Ended Party
- **WHEN** a user joins an Ended Party by code or link
- **THEN** the user may view the read-only history but cannot request, vote or rate

#### Scenario: Non-member opens the Party page
- **WHEN** a logged-in user who has completed signup and is not a Member opens a Party's page by its link or QR code
- **THEN** they become a Guest of the Party and see its Queue

#### Scenario: Default Party
- **WHEN** a logged-in user who is not a Member is sent to the instance's default Party
- **THEN** they become a Guest of that Party

#### Scenario: Not logged in
- **WHEN** an anonymous visitor opens a Party's link
- **THEN** they are asked to log in and, after login and signup, return to the Party and are joined

#### Scenario: Unknown code in a link
- **WHEN** a logged-in user opens a Party URL whose code matches no Party
- **THEN** no membership is created and the join form reports that the Party was not found

#### Scenario: Guessing party codes
- **WHEN** a user submits more join attempts than the per-user limit allows within a minute
- **THEN** further attempts are refused until the limit recovers, and other users are unaffected

### Requirement: Login with social credentials
The system SHALL let users log in with the social providers enabled by an instance admin (Discord, Twitch, Steam, Spotify, Google, Facebook and Laravel Passport). Laravel Passport login SHALL use the server URL and display name the admin configured. Logging in SHALL NOT by itself grant any Party Role. One social identity (provider and external account id) SHALL map to exactly one user, even when the login callback is submitted twice at once. Logging out SHALL require a POST request with CSRF protection.

#### Scenario: Login with an enabled provider
- **WHEN** a user completes login with an enabled social provider
- **THEN** the user is logged in and can join Parties

#### Scenario: Login with Google or Facebook
- **WHEN** a user completes login with an enabled Google or Facebook provider
- **THEN** the user is logged in through that provider, and joining a Party still requires a social login (no local or no-auth login exists)

#### Scenario: Login with Laravel Passport
- **WHEN** an admin has enabled Laravel Passport with a server URL and the display name "Mintopia ID", and a user completes login through it
- **THEN** the login page showed a "Mintopia ID" button and the user is logged in

#### Scenario: Disabled provider
- **WHEN** a social provider has not been enabled by an admin
- **THEN** it is not offered on the login page and its login route is refused

#### Scenario: Double-submitted callback
- **WHEN** the same provider callback for a new identity is processed twice concurrently
- **THEN** one user and one linked account exist afterwards, and both requests log in as that user

#### Scenario: Logout by link
- **WHEN** a page embeds a GET request to the logout path
- **THEN** the user stays logged in

### Requirement: Party Roles
Every Member SHALL hold exactly one Party Role: Host, Moderator, VIP or Guest. Each Party SHALL have exactly one Host, its creator. There SHALL be no co-hosts or additional owners, and the Host role SHALL NOT be granted to another Member. The Host SHALL control the Party's settings and Player and SHALL be able to do everything a Moderator can.

#### Scenario: Single role
- **WHEN** a user joins a Party
- **THEN** they hold exactly one Party Role in it

#### Scenario: No second Host
- **WHEN** the Host attempts to give another Member the Host role through the UI or the API
- **THEN** the system refuses, and the Party still has exactly one Host

### Requirement: Signup completion
A new user SHALL complete signup before creating or joining a Party, requesting, voting or using the API with their session. Signup needs a valid nickname, plus consent when the instance has a terms of service URL or a privacy policy URL in its site settings. When neither URL is configured, the consent time SHALL be set when the account is created and no consent step SHALL be shown.

#### Scenario: Signup skipped
- **WHEN** a user who has not completed signup opens the create-party or join page, or calls the API with their session
- **THEN** they are redirected to signup, or the API returns 403 with a signup-required error code

#### Scenario: Terms configured
- **WHEN** an admin has set a terms of service URL and a new user signs up
- **THEN** the user must accept the terms before continuing, and the acceptance time is stored

#### Scenario: Only a privacy policy configured
- **WHEN** an admin has set a privacy policy URL in the site settings but no terms URL, and a new user signs up without ticking consent
- **THEN** signup is refused with an error naming the Privacy Policy

#### Scenario: Both documents configured
- **WHEN** both URLs are set and a new user does not tick consent
- **THEN** the error reads "You must agree to the Terms and Conditions and Privacy Policy"

#### Scenario: No terms configured
- **WHEN** neither a terms of service URL nor a privacy policy URL is set and a new user logs in for the first time
- **THEN** the consent time is set at account creation and signup asks only for a nickname

#### Scenario: Signup and logout reachable
- **WHEN** a user who has not completed signup opens the signup page or logs out
- **THEN** the request is allowed

## ADDED Requirements

### Requirement: Account merge by email on login
When a social login matches no linked account but the provider returns an email, the system SHALL join the login to the one user whose linked account has the same email, ignoring case and surrounding spaces. It SHALL NOT merge when the provider says the email is unverified or when more than one user matches. Each merge SHALL be audited and the user told which provider was added.

#### Scenario: Same email, different provider
- **WHEN** a user who logged in with Discord as `Sam@Example.com` later logs in with Twitch for the first time and Twitch returns `sam@example.com`
- **THEN** the Twitch account is linked to the same user, they are logged in as that user, an admin audit entry records the merge, and they see a message that Twitch was added

#### Scenario: Unverified email
- **WHEN** a provider returns an email together with an explicit unverified flag
- **THEN** no merge happens and a new user is created as for any first login

#### Scenario: Ambiguous email
- **WHEN** linked accounts of two different users have the email the provider returned
- **THEN** no merge happens, a new user is created and a warning is logged

#### Scenario: Suspended match
- **WHEN** the matching user is suspended
- **THEN** the login is refused as for a suspended user

#### Scenario: No email
- **WHEN** a provider returns no email, as Steam does
- **THEN** no merge is attempted

#### Scenario: Explicit linking unchanged
- **WHEN** a logged-in user links another provider from their profile
- **THEN** the account is linked to them without any email comparison

### Requirement: Nickname rules
A nickname SHALL be 1 to 32 characters after trimming and SHALL be unique across users, ignoring case. The rule SHALL apply on signup, on profile edit and on admin edit, and the database SHALL enforce it.

#### Scenario: Duplicate nickname
- **WHEN** a user tries to take the nickname "DJ Sam" while another user is called "dj sam"
- **THEN** the change is refused with a validation error and the nickname is unchanged

#### Scenario: Keeping your own nickname
- **WHEN** a user saves their profile with their current nickname in different case
- **THEN** the change is accepted

#### Scenario: Too long
- **WHEN** a user enters a 33-character nickname
- **THEN** the change is refused, and the signup and profile inputs do not accept more than 32 characters

#### Scenario: Existing duplicates
- **WHEN** the uniqueness rule is introduced on an instance that already has users "Alex" and "alex"
- **THEN** the migration keeps the earlier user's nickname, renames the later one to a unique variant within 32 characters, and the unique index is then created

### Requirement: Members list
The Host and Moderators SHALL be able to list a Party's Members with a search by nickname, a role filter, sorting by nickname, role or joined time, paging, and columns for nickname, role, Ban status and joined time. Role changes from the list SHALL follow the role assignment rules.

#### Scenario: Filter by role
- **WHEN** a Moderator filters the Members list by VIP
- **THEN** only VIP Members are listed, paged, with their joined time

#### Scenario: Guest denied
- **WHEN** a Guest opens the Members list
- **THEN** access is refused

### Requirement: Member detail
The Host and Moderators SHALL be able to open a Member's detail page showing their role, Ban status, joined time, their Requests, and their Votes with Track, direction and time. The Votes SHALL be searchable by Track and paged. Ban and role actions SHALL be available there under the same rules as on the list.

#### Scenario: Vote history
- **WHEN** a Moderator opens a Member's detail page
- **THEN** it lists that Member's Votes in the Party with Track, up or down and time, newest first

#### Scenario: Another Party's Member
- **WHEN** a Moderator of one Party requests the detail page of a Member of another Party
- **THEN** access is refused
