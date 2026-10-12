## MODIFIED Requirements

### Requirement: Stats exclude non-counted activity
Stats SHALL be derived from the Party's recorded Requests, Votes and Plays. Rejected and Removed Requests MUST NOT count towards played statistics, and system Requests created from the Fallback Playlist SHALL be excluded from requester rankings. A Track replayed through the Previous control is not a Play and MUST NOT count in any statistic or in the Party Export.

#### Scenario: Fallback excluded from requesters
- **WHEN** a Track plays from the Fallback Playlist
- **THEN** it counts towards time played and top Tracks but appears in no requester ranking

#### Scenario: Replay not counted
- **WHEN** the Host replays the previous Track with the Previous control
- **THEN** Live Stats, leaderboards and the Party Export show that Track's play count and time played unchanged
