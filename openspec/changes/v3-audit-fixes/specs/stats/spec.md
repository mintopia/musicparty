## MODIFIED Requirements

### Requirement: Real-time updates
Live Stats SHALL update in real time as Requests are made, Votes are cast and Plays complete, without the viewer reloading. Recalculation SHALL happen outside the request that caused it, and bursts of activity in a Party SHALL be coalesced into one recalculation, so that requesting, voting and rating are neither slowed down nor failed by Stats. Viewers SHALL see the update within about ten seconds. The data sent SHALL contain no member IDs or secrets beyond what is visible to Members in the Party.

#### Scenario: Vote updates stats
- **WHEN** a Member casts a Vote while another Member is viewing Live Stats
- **THEN** the viewer's most upvoted or most downvoted figures update without a reload

#### Scenario: Play completes
- **WHEN** a Track finishes playing
- **THEN** total time played and top Tracks update for connected viewers

#### Scenario: Burst of Votes
- **WHEN** fifty Votes are cast in a Party within a few seconds
- **THEN** Live Stats are recalculated once for that burst, and every Vote request completes without waiting for the recalculation

#### Scenario: Concurrent recalculation
- **WHEN** two recalculations for the same Party run at the same moment
- **THEN** both complete without error and the stored Live Stats reflect the latest data
