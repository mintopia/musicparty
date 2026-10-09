## ADDED Requirements

### Requirement: Instance Theme
The system SHALL provide an Instance Theme: a site-wide set of design tokens that applies to every page of the installation unless a Party Theme overrides a token. The Instance Theme defaults SHALL reproduce the look of the v2 guest pages (the current Tabler values).

#### Scenario: Default look
- **WHEN** a fresh installation renders a guest page with no Instance Theme edits and no Party Theme
- **THEN** the page uses the default token values that match the v2 look and feel

#### Scenario: Admin edit applies everywhere
- **WHEN** an admin changes a token in the Instance Theme
- **THEN** all pages without a Party Theme override for that token use the new value, without a rebuild or redeploy

### Requirement: Party Theme overrides
The system SHALL let a Party's Host override a subset of Instance Theme tokens for that Party: the colour tokens primary, accent, background, surface, text and danger. Tokens outside this set MUST NOT be overridable by a Host. A token without an override SHALL inherit the Instance Theme value.

#### Scenario: Host overrides a colour
- **WHEN** the Host sets the primary colour in the Party Theme
- **THEN** that Party's guest pages and TV screen use the new primary colour and other Parties are unaffected

#### Scenario: Non-overridable token rejected
- **WHEN** a Host attempts to override a token outside the allowed set
- **THEN** the request is rejected with a validation error and the Party Theme is unchanged

#### Scenario: Reset to inherit
- **WHEN** the Host clears an override
- **THEN** the token reverts to the Instance Theme value

#### Scenario: Only the Host edits
- **WHEN** a Moderator, VIP or Guest attempts to change the Party Theme
- **THEN** the change is refused

### Requirement: Curated fonts
The system SHALL offer a curated list of fonts for the Instance Theme and Party Theme. Selecting a font outside the list MUST be refused.

#### Scenario: Choose curated font
- **WHEN** the Host picks a font from the curated list
- **THEN** the Party's pages render in that font

#### Scenario: Unknown font
- **WHEN** a font value not in the curated list is submitted
- **THEN** it is rejected with a validation error

### Requirement: Logo and background uploads
A Host SHALL be able to upload a logo and a background image for a Party Theme. Uploads MUST be validated for type and size, and rejected if they are not accepted image types. Removing an upload SHALL revert to the Instance Theme asset.

#### Scenario: Upload logo
- **WHEN** the Host uploads a valid logo image
- **THEN** the logo appears in that Party's header and on its TV screen

#### Scenario: Invalid upload
- **WHEN** the Host uploads a file that is not an accepted image type or exceeds the size limit
- **THEN** the upload is rejected and the previous asset remains

### Requirement: TV layout presets
The system SHALL provide a fixed set of TV layout presets for the TV screen, selectable by the Host in the Party Theme. The Host MUST NOT be able to supply custom layout markup or styles.

#### Scenario: Select preset
- **WHEN** the Host selects a TV layout preset
- **THEN** the TV screen renders using that preset

#### Scenario: Unknown preset
- **WHEN** a value that is not a defined preset is submitted
- **THEN** it is rejected with a validation error

### Requirement: Colour Scheme
Each user SHALL be able to choose a Colour Scheme of light, dark or system, applied on top of whichever theme is in effect. Anonymous visitors SHALL be able to choose one too, and the choice SHALL persist on that device. The default SHALL be system.

#### Scenario: System preference
- **WHEN** a visitor with Colour Scheme "system" has an operating system set to dark
- **THEN** pages render the dark variant of the effective theme

#### Scenario: Explicit choice persists
- **WHEN** a user selects "light"
- **THEN** pages render the light variant on subsequent visits regardless of the system preference

### Requirement: No raw CSS
The system MUST NOT accept, store or render raw CSS, HTML or script from any theme input, from any Host, admin or Mod. Theme values SHALL be constrained to typed tokens (colours, curated font, uploaded images, preset identifiers).

#### Scenario: CSS injection attempt
- **WHEN** a colour token value contains CSS syntax such as a declaration, url() or a closing brace
- **THEN** it is rejected as invalid and nothing is stored

#### Scenario: Mod styling
- **WHEN** a Mod wants to influence appearance
- **THEN** it can only do so through Decorations using allow-listed style variants, never raw CSS

### Requirement: Contrast warnings
The system SHALL warn the editor when a chosen colour combination fails WCAG AA contrast (4.5:1 for normal text) for text on background and text on surface, in both light and dark Colour Schemes. A warning SHALL NOT block saving.

#### Scenario: Low contrast warning
- **WHEN** the Host picks a text colour with a contrast ratio below 4.5:1 against the background
- **THEN** a warning identifying the failing pair is shown and the Host can still save

#### Scenario: Passing combination
- **WHEN** all checked pairs meet WCAG AA
- **THEN** no warning is shown

### Requirement: Desktop look-and-feel parity with v2
At viewports 768px wide and wider, the v3 guest pages SHALL match the layout and look and feel of the v2 guest pages, using the desktop reference screenshots in docs/design/v2-reference/ as the standard. This covers at least the party page with now playing and the Queue, search, the join flow and login.

#### Scenario: Default theme matches desktop reference
- **WHEN** a guest page is rendered at desktop width with the default Instance Theme
- **THEN** its layout, colours, typography and spacing match the corresponding desktop reference screenshot in docs/design/v2-reference/

### Requirement: Mobile-first guest layout
Below 768px, the guest pages SHALL use a new mobile-first layout that matches the approved mockups in docs/design/v3-mobile/. It SHALL share the desktop visual language (tokens, typography, card style, vote controls). The v2 mobile layout is not a reference.

#### Scenario: Mobile matches approved mockup
- **WHEN** a guest page is viewed on a phone-sized viewport
- **THEN** its layout matches the corresponding approved mockup in docs/design/v3-mobile/, with no horizontal scrolling

#### Scenario: Thumb-reachable primary actions
- **WHEN** a Member votes or requests on a phone
- **THEN** the controls are touch targets of at least 44×44 CSS pixels and are reachable without opening a side menu

#### Scenario: TV screen not optimised for phones
- **WHEN** the TV screen is opened on a phone
- **THEN** it remains usable, although its layout targets landscape screens of 1024px and wider

### Requirement: Themed guest pages and TV screen
Guest pages and the TV screen SHALL render with the effective theme (Instance Theme overlaid by the Party Theme, in the viewer's Colour Scheme). The TV screen SHALL be anonymous and read-only, and SHALL reflect Party Theme changes without a manual reload.

#### Scenario: Effective theme on guest page
- **WHEN** a Member opens a Party that has a Party Theme
- **THEN** the page uses the Party Theme overrides and falls back to the Instance Theme for the rest

#### Scenario: Live theme change on TV
- **WHEN** the Host changes the Party Theme while the TV screen is open
- **THEN** the TV screen updates to the new theme without a manual reload
