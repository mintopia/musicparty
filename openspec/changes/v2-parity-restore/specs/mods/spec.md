## MODIFIED Requirements

### Requirement: Future Mods are non-goals but possible
The Mod design SHALL be able to support later Mods without changing core, such as AI track suggestion or Fallback generation. Those Mods are non-goals and MUST NOT be built in this change. The Trust Score and Whamageddon Mods SHALL be built using only the documented extension points.

#### Scenario: Extension points suffice
- **WHEN** a later Mod needs to adjust Scores, schedule system Requests or display badges
- **THEN** it can do so using only the documented extension points

#### Scenario: Out of scope
- **WHEN** this change is delivered
- **THEN** no AI suggestion or Fallback generation Mod is shipped, and the Trust Score and Whamageddon Mods are

## ADDED Requirements

### Requirement: Trust Score Mod
The system SHALL ship a first-party Trust Score Mod. While enabled, a Scheduled action SHALL recalculate each Member's trust score hourly from the positive Votes and positive Ratings that Members gave to each other's Requests in a configurable window, anchored on a trusted Member. The Host and Moderators SHALL see trust scores on the Members pages.

#### Scenario: Recalculation
- **WHEN** the Mod is enabled and its hourly Scheduled action runs
- **THEN** every Member of the Party has a trust score computed from Votes and Ratings in the window, and the run is recorded in the Party Log

#### Scenario: Trust flows from the anchor
- **WHEN** the anchor Member has upvoted Requests by Member A and nobody has upvoted Requests by Member B
- **THEN** Member A's trust score is higher than Member B's

#### Scenario: Default settings
- **WHEN** the Host enables the Mod
- **THEN** the anchor is the Host, the window is 7 days and Score weighting is off

#### Scenario: Not public
- **WHEN** a Guest views the Queue or calls the public API
- **THEN** no trust score is exposed

### Requirement: Trust Score weighting
When the Host turns on Score weighting, the Trust Score Mod SHALL supply a Score Modifier that adds to each Queued Request a bonus proportional to its requester's trust score, capped by a configurable maximum. Turning weighting off or disabling the Mod SHALL remove the bonus.

#### Scenario: Weighting on
- **WHEN** weighting is on with a maximum bonus of 2 and a Request's requester has the Party's highest trust score
- **THEN** that Request's Score is its Vote total plus 2

#### Scenario: Weighting off
- **WHEN** weighting is off
- **THEN** trust scores do not change any Score

### Requirement: Whamageddon Mod
The system SHALL ship a first-party Whamageddon Mod. Enabling it SHALL add its configured Track as a system Request with the Mod's Decoration. Its hourly Scheduled action SHALL add a configurable Score bonus while the Request is Queued. Once the Track has played, the action SHALL add it again after a configurable interval. Disabling the Mod SHALL remove the Request if it is still Queued.

#### Scenario: Enable
- **WHEN** the Host enables Whamageddon with its default Track
- **THEN** a system Request for that Track is Queued with the Whamageddon badge and the Party Log names the Mod

#### Scenario: Hourly bonus
- **WHEN** an hour passes while the Whamageddon Request is Queued and outside the quiet hours
- **THEN** its Score rises by the configured bonus (1 by default) and the change is in the Party Log

#### Scenario: Played and re-added
- **WHEN** the Whamageddon Track played more than the re-add interval ago (2 hours by default)
- **THEN** the next hourly run adds it to the Queue again as a new system Request

#### Scenario: Disable
- **WHEN** the Host disables the Mod while its Request is Queued
- **THEN** the Request is Removed and the Party Log names the Mod

#### Scenario: Paused Party
- **WHEN** the Party is Paused
- **THEN** no bonus is added and nothing is re-added

### Requirement: Whamageddon quiet hours
The Whamageddon Mod SHALL add no bonus during configurable quiet hours (02:00 to 10:00 by default, in the instance's time zone). From a configurable evening hour (23:00 by default) the Whamageddon Request SHALL NOT be eligible for selection until the end of the quiet hours.

#### Scenario: Overnight hold
- **WHEN** the hourly run happens at 23:30
- **THEN** the Whamageddon Request is not eligible for selection until 10:00 the next morning

#### Scenario: Morning release
- **WHEN** the hourly run happens at 10:05
- **THEN** the Request is eligible again
