## MODIFIED Requirements

### Requirement: Instance Theme
The system SHALL provide an Instance Theme: a site-wide set of design tokens that applies to every page of the installation unless a Party Theme overrides a token. The Instance Theme defaults SHALL reproduce the look of the v2 guest pages (the current Tabler values). There SHALL be exactly one Instance Theme: no library of named themes, no switching between saved themes and no separate navigation colour.

#### Scenario: Default look
- **WHEN** a fresh installation renders a guest page with no Instance Theme edits and no Party Theme
- **THEN** the page uses the default token values that match the v2 look and feel

#### Scenario: Admin edit applies everywhere
- **WHEN** an admin changes a token in the Instance Theme
- **THEN** all pages without a Party Theme override for that token use the new value, without a rebuild or redeploy

#### Scenario: No theme library
- **WHEN** an admin opens the Theme page
- **THEN** it edits the one Instance Theme, and there is no list of named themes to create, activate or delete

### Requirement: Themed guest pages and TV screen
Every guest page of a Party (Queue, Search, History, song and member detail, management pages and the Player page) and its TV screen SHALL render with the effective theme: the Instance Theme overlaid by the Party Theme, in the viewer's Colour Scheme. The TV screen SHALL be anonymous and read-only, and SHALL reflect Party Theme changes without a manual reload.

#### Scenario: Effective theme on guest page
- **WHEN** a Member opens a Party that has a Party Theme
- **THEN** the page uses the Party Theme overrides and falls back to the Instance Theme for the rest

#### Scenario: Every Party page themed
- **WHEN** a Party Theme sets the primary colour and a Member opens the Party's Search, History and Members pages
- **THEN** each page uses that primary colour, and pages outside the Party do not

#### Scenario: Live theme change on TV
- **WHEN** the Host changes the Party Theme while the TV screen is open
- **THEN** the TV screen updates to the new theme without a manual reload
