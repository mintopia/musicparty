# Spec Delta

## Purpose

The Inertia web shell that every page shares: flash messages, the Party menu and breadcrumbs, the Now Playing header and mobile mini-player, the login and signup screens, and keeping a Party page's session alive.

## ADDED Requirements

### Requirement: Flash messages
Every web action that confirms success or reports a failure SHALL flash it under one of the keys `success`, `error`, `info` or `warning`, and the shell SHALL show it once on the next page, including the login page. No page SHALL read a flash key that the server does not share.

#### Scenario: Settings saved
- **WHEN** the Host saves Party settings
- **THEN** the next page shows a success message

#### Scenario: Logged out
- **WHEN** a user logs out
- **THEN** the login page shows "You have been logged out"

#### Scenario: Account created
- **WHEN** a new user completes signup
- **THEN** the next page shows "Your account has been created"

#### Scenario: Failure on a moderation page
- **WHEN** a Blocklist, Members, Pending or Mods action fails validation or authorisation
- **THEN** the page shows the error message

### Requirement: Party menu
On every page of a Party, the sidebar on desktop and the menu on mobile SHALL list that Party's sections. Members see Queue, Search, History and TV Mode. Moderators also see Users, Moderation, Pending and Party Log. The Host also sees Settings, Theme, Player Tokens and, for a Browser Player Party, Web Player. The current section SHALL be marked.

#### Scenario: Guest menu
- **WHEN** a Guest opens a Party page
- **THEN** the menu shows Queue, Search, History and TV Mode and no management entries

#### Scenario: Host menu
- **WHEN** the Host opens a Party page
- **THEN** the menu also shows Users, Moderation, Pending, Party Log, Settings, Theme and Player Tokens

#### Scenario: Web Player entry
- **WHEN** the Host opens a Party whose Player is not the Browser Player
- **THEN** no Web Player entry is shown

### Requirement: Breadcrumbs
Every page SHALL show breadcrumbs that end with the page's visible name. The Party page SHALL end with the name of the current section (Queue, Search, History or Party), never an internal component name.

#### Scenario: Search section
- **WHEN** a Member opens the Search section of a Party
- **THEN** the breadcrumbs end with "Search"

### Requirement: Now Playing header on every Party page
Every page of a Party SHALL show the Now Playing header. The header SHALL show the blurred album art as a backdrop, the cover, Track name, artists and requester, a progress bar with elapsed and total time that advances every second, and the net rating. When nothing is playing it SHALL show "There is nothing playing". For the Host it SHALL include icon playback controls (Previous, Play or Pause, Next) for the controls the Player supports.

#### Scenario: Header on a management page
- **WHEN** a Moderator opens the Party Log page
- **THEN** the Now Playing header is shown above the page content

#### Scenario: Progress advances
- **WHEN** a Track is playing and one second passes
- **THEN** the elapsed time and progress bar advance without a server request

#### Scenario: Nothing playing
- **WHEN** a Party has no Playing Track
- **THEN** the header reads "There is nothing playing"

#### Scenario: Unsupported control hidden
- **WHEN** the Host views a Party whose Player does not support Previous
- **THEN** the Previous control is disabled

### Requirement: Party header placement
The Party name and party code card SHALL appear only on the Party section of the Party page, not above the Queue, Search or History sections.

#### Scenario: Queue section
- **WHEN** a Member opens the Queue section
- **THEN** the Now Playing header is followed directly by the Queue

### Requirement: Mobile mini-player
Below 768px the Party pages SHALL show a sticky mini Now Playing bar with a progress bar, and tapping its expand control SHALL open the full Now Playing view.

#### Scenario: Expand
- **WHEN** a Member taps expand on the mini bar
- **THEN** the full Now Playing view opens, and closing it returns to the same page position

### Requirement: Login page
The login page SHALL show the site name and the site logo for the viewer's Colour Scheme from the site settings, with the admin's cover image beside the providers on wide screens. It SHALL fit the viewport at every width with no horizontal scrolling.

#### Scenario: Custom site name
- **WHEN** an admin sets the site name to "LAN Tunes"
- **THEN** the login page and its copyright line show "LAN Tunes"

#### Scenario: No sideways scroll
- **WHEN** the login page is shown at 1280px with a cover image
- **THEN** the page has no horizontal scrollbar

### Requirement: Signup page
The signup page SHALL match v2. It SHALL have the heading "Create Account", show the provider avatar, and prefill the nickname with a hint about its rules. It SHALL show a terms checkbox labelled with the documents the admin configured ("Terms and Conditions", "Privacy Policy" or both, linked). It SHALL offer a Cancel action that logs the user out.

#### Scenario: Cancel signup
- **WHEN** a new user chooses Cancel on the signup page
- **THEN** they are logged out and returned to the login page

### Requirement: Session keep-alive on Party pages
While a Party page is open and visible, the client SHALL ping the server about every 60 seconds so the session does not expire while a Member watches the Queue.

#### Scenario: Long session
- **WHEN** a Member keeps the Queue open and visible for longer than the session lifetime
- **THEN** their next vote succeeds without logging in again
