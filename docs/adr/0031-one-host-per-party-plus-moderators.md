# One Host per Party plus Moderators; no co-owners

v2 allowed any number of Members with the role owner. v3 has exactly one Host, and the Party Roles are Host, Moderator, VIP and Guest (`openspec/specs/membership/spec.md`). The triage asked whether co-hosts should return (dec-7, diff-10).

We considered multiple Hosts and a Host-transfer action. Several Hosts make "who controls the Player and settings" ambiguous, and every authorisation check and Party Log entry would have to cope with it. The Moderator role already gives delegated Queue management and Bans.

Decision:
- A Party has exactly one Host. There are no co-hosts and no multiple owners.
- Moderators cover delegated management of the Queue and Members.
- Transferring the Host role is not provided and is out of scope unless asked for.
- When a Host is unavailable, an admin uses act-as-Host or impersonation (ADR-0025).

Consequences: a Party whose Host leaves mid-event cannot hand over settings and Player control except through an admin. Moderators cannot reach Party settings or the Player, by design.
