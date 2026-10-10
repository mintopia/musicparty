## ADDED Requirements

### Requirement: Selection modes
Each Party SHALL have a selection mode, set by the Host: deterministic or weighted. Under deterministic selection the system SHALL choose the eligible Queued Request with the highest score, breaking ties by oldest request time. Under weighted selection the system SHALL choose randomly from eligible Queued Requests with probability proportional to their positive score, with Requests of zero or negative score eligible only when no Request has a positive score.

#### Scenario: Deterministic highest score
- **WHEN** selection runs in deterministic mode with Requests scoring 3, 5 and 1
- **THEN** the Request scoring 5 is chosen

#### Scenario: Deterministic tie
- **WHEN** two eligible Requests share the highest score
- **THEN** the older Request is chosen

#### Scenario: Weighted proportions
- **WHEN** selection runs repeatedly in weighted mode over Requests scoring 9 and 1
- **THEN** the first is chosen in roughly nine out of ten selections

#### Scenario: No positive scores
- **WHEN** weighted selection runs and every eligible Request has a score of zero or less
- **THEN** a Request is still chosen and the Queue never stalls

#### Scenario: Empty Queue
- **WHEN** selection runs and no Request is eligible
- **THEN** no Request is chosen and the Fallback Playlist exhaustion rules apply

### Requirement: Eligibility and not_before
A Request SHALL be eligible for selection only when it is Queued and its not-before time, if any, has passed. Pending, Rejected, Removed and Played Requests, and Requests from Banned Members that the Host has chosen to purge, SHALL NOT be selected.

#### Scenario: Not yet eligible
- **WHEN** a Request has a not-before time in the future
- **THEN** it is skipped by selection until that time

#### Scenario: Pending skipped
- **WHEN** a Pending Request has the highest score
- **THEN** it is not selected

### Requirement: Score Modifiers
Enabled Mods MAY adjust a Request's score through Score Modifiers. Adjustments SHALL be additive, SHALL be applied identically for deterministic and weighted selection, and SHALL be visible with the resulting score. A failing Score Modifier MUST NOT prevent selection; its adjustment SHALL be treated as zero and the failure recorded in the Party Log.

#### Scenario: Positive modifier
- **WHEN** a Score Modifier adds +2 to a Request
- **THEN** selection uses the Vote sum plus 2

#### Scenario: Modifier failure
- **WHEN** a Score Modifier raises an error during selection
- **THEN** selection proceeds with that adjustment treated as zero and the failure is logged

#### Scenario: No Mods enabled
- **WHEN** no Mods are enabled
- **THEN** a Request's score equals its Vote sum

### Requirement: Up Next lock
When a Request is selected, it SHALL become the Party's single Up Next Request and be locked. While locked, Votes MUST NOT change which Request is Up Next, new Requests MUST NOT replace it, and its score MUST NOT be altered by Score Modifiers. There SHALL be at most one Up Next Request per Party at any time. A locked Up Next Request SHALL be released only when it starts Playing, or when it is explicitly removed and the Player can withdraw it.

#### Scenario: Locking
- **WHEN** a Request is selected
- **THEN** it becomes Up Next and the other Queued Requests remain Queued

#### Scenario: Votes after lock
- **WHEN** a higher-scoring Request appears after another is locked as Up Next
- **THEN** the locked Request remains Up Next

#### Scenario: Single Up Next
- **WHEN** an Up Next Request already exists
- **THEN** selection does not run again until it has started Playing

#### Scenario: Up Next becomes Playing
- **WHEN** the Up Next Track begins playing
- **THEN** its Request becomes Playing and a new selection becomes due according to the Player's Feed Mode

### Requirement: Selection timing by Feed Mode
Selection timing SHALL depend on the Player's Feed Mode. In just-in-time mode the system SHALL select the next Request and lock it as Up Next shortly before the current Track ends (about 15 seconds before), then send it to the Player. In ahead mode the system SHALL keep exactly one Up Next Request in the Music Provider's queue, selecting a replacement after each Track change. A Party with no current Track SHALL select immediately when it goes Live or when the Queue first has an eligible Request.

#### Scenario: Just-in-time selection
- **WHEN** a Party using a just-in-time Player is about 15 seconds from the end of the current Track
- **THEN** the system selects the next Request, locks it as Up Next and sends it to the Player

#### Scenario: Ahead refill
- **WHEN** the current Track changes in a Party using an ahead Player
- **THEN** a new Up Next Request is selected and placed so exactly one Track is queued ahead

#### Scenario: Votes still count until the lock
- **WHEN** Votes arrive after a just-in-time Party's previous selection but before the next one
- **THEN** they are counted in the next selection

#### Scenario: Idle start
- **WHEN** a Party goes Live with nothing playing and Requests are Queued
- **THEN** selection runs immediately and the first Request starts

#### Scenario: Selection with Fallback
- **WHEN** selection is due and the Queue has no eligible Request but the Fallback Playlist does
- **THEN** a Fallback Request is created, selected and locked as Up Next

### Requirement: Selection is single-writer
For a given Party, selection MUST NOT run concurrently with itself, so that two Requests cannot both become Up Next. Repeated triggers for the same selection moment SHALL result in a single selection.

#### Scenario: Duplicate trigger
- **WHEN** two triggers for selection arrive at the same time for one Party
- **THEN** exactly one Up Next Request results

#### Scenario: Selection logged
- **WHEN** a Request is selected
- **THEN** the selection mode, the chosen Request and its score at selection time are recorded for Live Stats and diagnostics
