## ADDED Requirements

### Requirement: Operator Party commands
The application SHALL provide Artisan commands for Operators: `party:check {code?}` reports and repairs a Party's health, `party:fallback` ticks every Live Party whose last playback tick is over 90 seconds old, `party:updatestate {code}` runs one playback tick now, `party:pushupdate {code}` re-broadcasts the Party's state, `party:refreshaccesstokens` refreshes Host tokens near expiry, and `party:calculatetrust {code}` recalculates trust scores. Unknown codes SHALL fail with a non-zero exit.

#### Scenario: Health check
- **WHEN** an Operator runs `party:check ABCD` for a Live Party whose Fallback Playlist cache is stale
- **THEN** the command refreshes the cache, re-runs the Fallback Playlist gate check, and prints the Player health, Host account status and playable Fallback Track count

#### Scenario: All Live Parties
- **WHEN** `party:check` runs without a code
- **THEN** every Live Party is checked

#### Scenario: Unknown Party
- **WHEN** `party:pushupdate ZZZZ` names no Party
- **THEN** the command prints an error and exits non-zero

#### Scenario: No force command
- **WHEN** an Operator lists the `party` commands
- **THEN** there is no `party:force` command

### Requirement: Console log mirroring for operator commands
While an operator `party:*` command runs, log messages the application writes SHALL also be shown in that command's output, at or above the level matching its verbosity. Logging from any other command, queue worker or scheduler SHALL NOT be written to standard output.

#### Scenario: Verbose run
- **WHEN** an Operator runs `party:check ABCD -v`
- **THEN** info-level log lines written during the check appear in the command output

#### Scenario: Worker unaffected
- **WHEN** Horizon processes a job that logs a message
- **THEN** nothing is written to standard output

### Requirement: Proactive Host token refresh
The scheduler SHALL refresh, every minute, Host Music Provider access tokens that expire within the next 5 minutes, using the same single-refresh lock as on-demand refresh, so a hand-off or poll does not wait on a refresh.

#### Scenario: Near expiry
- **WHEN** a Host's Spotify access token expires in 3 minutes
- **THEN** the next scheduled run refreshes it and the new expiry is stored

#### Scenario: Refresh rejected
- **WHEN** the scheduled refresh is rejected with `invalid_grant`
- **THEN** the account is marked as needing re-linking, as for an on-demand refresh
