# mods Specification

## Purpose
Synced from the archived v3-rewrite change.

## Requirements

### Requirement: Mod registration
Mods SHALL be first-party code shipped with the application and registered at boot. The system MUST NOT load third-party Mod code at runtime. Each registered Mod SHALL declare an identifier, name, description and typed settings.

#### Scenario: Registered Mod appears
- **WHEN** a Mod is registered
- **THEN** it appears in the Mods catalogue and is available for Hosts to enable

#### Scenario: No runtime code loading
- **WHEN** a user uploads or references external code as a Mod
- **THEN** there is no mechanism to load it

### Requirement: Per-Party enablement
A Host SHALL be able to enable and disable each available Mod for their own Party. A Mod SHALL have no effect on a Party where it is not enabled. Only the Host (or an admin acting as Host) MAY change enablement or settings.

#### Scenario: Enable Mod
- **WHEN** the Host enables a Mod for the Party
- **THEN** the Mod's extension points apply to that Party only

#### Scenario: Not enabled
- **WHEN** a Mod is not enabled for a Party
- **THEN** none of its rules, modifiers, actions, decorations or listeners run for it

#### Scenario: Moderator refused
- **WHEN** a Moderator attempts to enable a Mod or change its settings
- **THEN** it is refused

### Requirement: Typed Mod settings
Each Mod SHALL define typed, validated settings with defaults. Settings are per Party. Invalid values MUST be rejected, and secrets in settings MUST be stored encrypted and not displayed back in full.

#### Scenario: Valid settings
- **WHEN** the Host saves settings that satisfy the Mod's declared types
- **THEN** they are stored and used

#### Scenario: Invalid settings
- **WHEN** the Host submits a value of the wrong type or out of range
- **THEN** it is rejected with a validation error and the previous settings remain

#### Scenario: Defaults
- **WHEN** a Mod is first enabled
- **THEN** its settings hold their declared defaults

### Requirement: Request Rules extension point
A Mod SHALL be able to supply Request Rules that judge a new Request as accept, reject or hold. A reject SHALL stop the Request entering the Queue and tell the requester why. A hold SHALL place the Request in Pending for Host or Moderator approval, even when the Party has no other holding rule.

#### Scenario: Rule rejects
- **WHEN** a Request Rule rejects a Request
- **THEN** the Request is not queued and the requester is told it was rejected with a reason

#### Scenario: Rule holds
- **WHEN** a Request Rule holds a Request
- **THEN** the Request becomes Pending and appears for Host and Moderators to approve or reject

### Requirement: Request Rule precedence
When several Request Rules apply to a Request, reject SHALL take precedence over hold, and hold over accept.

#### Scenario: Precedence
- **WHEN** one rule holds a Request and another rejects it
- **THEN** the Request is rejected

### Requirement: Request Rule failure handling
A Request Rule that fails or times out MUST NOT block requesting. The outcome SHALL follow the Mod's configured failure behaviour (accept or hold).

#### Scenario: Rule failure
- **WHEN** a Request Rule errors or times out
- **THEN** the Request is handled according to the Mod's failure setting and the failure is recorded in the Party Log

### Requirement: Score Modifiers extension point
A Mod SHALL be able to supply Score Modifiers that adjust a Request's Score. The adjustment SHALL be added to the sum of Votes, SHALL apply at selection time, and SHALL be reversible by disabling the Mod.

#### Scenario: Modifier applied
- **WHEN** an enabled Mod's Score Modifier adds +3 to a Request
- **THEN** that Request's Score is its Vote total plus 3 for selection

#### Scenario: Mod disabled
- **WHEN** the Mod is disabled
- **THEN** Scores revert to the Vote totals plus any other active adjustments

### Requirement: Scheduled actions extension point
A Mod SHALL be able to declare Scheduled actions that run while the Party is Live. A Scheduled action MAY create system Requests, which have no requester and pass through the Party's rules unless the Mod states otherwise. Scheduled actions MUST NOT run for Paused or Ended Parties. Each Scheduled action SHALL run at most once per interval per Party, even when scheduler runs overlap.

#### Scenario: Action creates a system Request
- **WHEN** a Scheduled action fires for a Live Party
- **THEN** a system Request is created in the Queue and attributed in the Party Log to the Mod

#### Scenario: Paused Party
- **WHEN** a Party is Paused
- **THEN** Scheduled actions do not run

#### Scenario: Overlapping runs
- **WHEN** two scheduler runs reach the same due Scheduled action at the same moment
- **THEN** the action runs once and creates at most one system Request

### Requirement: Decorations
A Mod SHALL be able to attach Decorations to Requests and Plays. A Decoration is structured data: a badge, label, icon, accent token and style variant from an allow-list. Decorations SHALL be carried in API responses and realtime payloads and rendered by core components. Raw HTML, CSS and script MUST NOT be accepted in a Decoration; values outside the allow-list MUST be rejected.

#### Scenario: Decoration displayed
- **WHEN** a Mod attaches a badge Decoration to a Request
- **THEN** the Queue shows the badge for all viewers of the Party and the Decoration is in the public Party payload

#### Scenario: Disallowed value
- **WHEN** a Mod produces a Decoration with an accent token or variant not in the allow-list
- **THEN** it is discarded and not shown

#### Scenario: Disabled Mod
- **WHEN** a Mod is disabled
- **THEN** its Decorations disappear from the Party

### Requirement: Build-time Vue slots
Mods SHALL be able to contribute Vue components to named slots defined by the core UI. Slot contributions are fixed at build time. A Mod's slot components SHALL render only for Parties where the Mod is enabled.

#### Scenario: Slot rendered
- **WHEN** an enabled Mod contributes a component to the settings slot
- **THEN** the component appears in that slot for the Host

#### Scenario: Not enabled
- **WHEN** the Mod is not enabled for the Party
- **THEN** its slot components do not render

### Requirement: Domain event listeners
A Mod SHALL be able to listen to domain events such as Request created, Vote cast, Track started, Track ended and Party state changed. A listener failure MUST NOT disrupt the core operation that raised the event.

#### Scenario: Listener runs
- **WHEN** a Request is created in a Party where the Mod is enabled
- **THEN** the Mod's listener is invoked

#### Scenario: Listener fails
- **WHEN** a Mod listener throws an error
- **THEN** the original operation still succeeds and the failure is recorded

### Requirement: Mod actions in the Party Log
Every automatic action a Mod takes that changes a Request, Score, the Queue or Party state SHALL produce a Party Log entry naming the Mod, the target and the outcome with a reason where available. Enabling, disabling and changing settings of a Mod SHALL also be logged.

#### Scenario: Auto-reject logged
- **WHEN** a Mod rejects a Request
- **THEN** the Party Log shows an entry naming the Mod, the Request and the reason

#### Scenario: Enablement logged
- **WHEN** the Host enables a Mod
- **THEN** the Party Log records who enabled it

### Requirement: AI Request Review Mod
The system SHALL ship a first-party Mod, AI Request Review, implemented as a Request Rule. It SHALL submit a Request's Track metadata to an AI classifier and map the result to accept, reject or hold. Settings SHALL include the driver (OpenAI or Jev), driver credentials, the review criteria or rubric, a threshold for rejecting and for holding, and the failure behaviour. The classifier SHALL be called through laravel/ai, for both drivers, unless an ADR records otherwise.

#### Scenario: Acceptable Track
- **WHEN** the classifier judges a Request acceptable
- **THEN** the Request proceeds to the Queue

#### Scenario: Unacceptable Track
- **WHEN** the classifier judges a Request above the reject threshold
- **THEN** the Request is rejected, the requester is told, and a Party Log entry records the verdict

#### Scenario: Uncertain Track
- **WHEN** the classifier's result falls between the hold and reject thresholds
- **THEN** the Request is held as Pending for Host or Moderators

#### Scenario: Driver choice
- **WHEN** the Host selects the Jev driver and supplies credentials
- **THEN** reviews use Jev, and when the OpenAI driver is selected they use OpenAI

#### Scenario: Provider outage
- **WHEN** the AI service is unreachable or returns an error
- **THEN** the Request follows the configured failure behaviour and the Party Log notes the failure

#### Scenario: No misconfiguration
- **WHEN** the Host enables the Mod without valid credentials for the selected driver
- **THEN** enabling is refused with a validation error

#### Scenario: Data minimisation
- **WHEN** a Request is sent for review
- **THEN** only Track metadata required for the review is sent, with no member identity or email

### Requirement: Future Mods are non-goals but possible
The Mod design SHALL be able to support later Mods without changing core: a trust score Mod (via Score Modifiers and Decorations), a Whamageddon Mod (via Scheduled actions and Decorations) and AI track suggestion or Fallback generation. These Mods are explicit non-goals for v3 and MUST NOT be built in this change.

#### Scenario: Extension points suffice
- **WHEN** a later Mod needs to adjust Scores, schedule system Requests or display badges
- **THEN** it can do so using only the documented extension points

#### Scenario: Out of scope
- **WHEN** v3 is delivered
- **THEN** no trust score, Whamageddon or AI suggestion Mod is shipped
