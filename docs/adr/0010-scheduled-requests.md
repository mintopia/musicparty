# Scheduled Requests: owner-only, API entry point, exempt from the request limit

A Scheduled Request is a Request with a `scheduled_for` time, also written to `not_before` so the existing hold applies. Once due it is selected before any unscheduled Request, earliest time first. A plain `not_before` hold (set by mods) does not jump the queue; only `scheduled_for` does.

**Moderator.** `PartyMemberRole` has a `moderator` code (seeded codes are `owner`, `moderator`, `vip`, `user`, `banned`). "Owner or moderator" means `Party::canBeModeratedBy()`: the Party's creator, or a member with the `owner` or `moderator` role. `vip` does not count. Both it and `canBeManagedBy()` (Owner only) derive from `Party::roleCodeFor()`, the single answer to which role a user holds in a Party.

**Entry point.** The existing request endpoint (`POST api/v1/parties/{party}/upcomingsongs`) takes an optional `scheduled_for`. A local time without an offset is read in the Party timezone. Members who are neither Owner nor Moderator get 403.

**Request limit.** Managers are already exempt from `TooManyRequestsRule`, so a Scheduled Request does not count against any limit.

Cancelling uses the existing delete policy, which allows Owners and Moderators.
