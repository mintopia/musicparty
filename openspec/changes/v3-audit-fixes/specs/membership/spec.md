## MODIFIED Requirements

### Requirement: Open join by party code
Anyone with a Party's party code SHALL be able to join that Party as a Member without an invitation or approval. Joining SHALL require the user to be logged in and SHALL be an explicit action: viewing a Party's page MUST NOT create a membership. A logged-in user who is not a Member and opens a Party's page SHALL be sent to the join form with the party code filled in. A joined user SHALL have exactly one Party Role in that Party, defaulting to Guest. Join attempts SHALL be rate limited per user.

#### Scenario: Joining with a valid code
- **WHEN** a logged-in user submits a valid party code
- **THEN** the user becomes a Member of the Party with the Guest role

#### Scenario: Unknown code
- **WHEN** a user submits a party code that matches no Party
- **THEN** the system reports that the Party was not found and no membership is created

#### Scenario: Rejoining
- **WHEN** an existing Member submits the party code again
- **THEN** no duplicate membership is created and the Member keeps their existing role

#### Scenario: Joining an Ended Party
- **WHEN** a user submits the code of an Ended Party
- **THEN** the user may view the read-only history but cannot request, vote or rate

#### Scenario: Non-member opens the Party page
- **WHEN** a logged-in user who is not a Member opens a Party's page by URL
- **THEN** no membership is created and the user is redirected to the join form with the code prefilled

#### Scenario: Guessing party codes
- **WHEN** a user submits more join attempts than the per-user limit allows within a minute
- **THEN** further attempts are refused until the limit recovers, and other users are unaffected
