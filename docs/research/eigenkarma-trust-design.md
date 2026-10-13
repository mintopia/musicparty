# Eigenkarma / Trust design (#388)

Status: **awaiting user sign-off.** Slice 17 (Trust implementation) must not start until sign-off is recorded on #388.

## What exists

`Party::calculateTrustScores` builds a matrix `V[owner][voter]` of raw positive vote and rating counts on a member's requested songs (user `0` stands in for system or unknown requesters), overwrites the diagonal with `-1` (`+1` for the trusted user), and calls `LinAlg::solve` against a unit vector on the trusted user.

- Each non-trusted row says `x_owner = Σ V[owner][voter] · x_voter`. That is the eigenvector equation with eigenvalue fixed at 1, but the columns are not normalised. Nothing makes 1 the dominant eigenvalue, so scores can go negative, blow up, or (when endorsements form an exact cycle) make the matrix singular. Singular input throws.
- Downvotes are ignored. Self-votes are discarded by the diagonal overwrite.
- It is order N² in memory and N³ in time, and only runs via `party:calculatetrust` over a 7-day window. Nothing reads `trustscore`.

## Verdict on the model

Eigenvector centrality is the right *family* (a member is trusted if trusted members endorse their requests), but the current formulation is not a sound instance of it. Use **personalised PageRank (EigenTrust style)** instead:

1. Endorsement `E[voter → owner]` = positive votes plus positive ratings by `voter` on `owner`'s requests, in the window. Exclude self-endorsement and exclude owner `0`.
2. Normalise per voter: each voter's outgoing endorsements sum to 1, so a voter's influence is a fixed budget, and spamming votes does not add weight.
3. Iterate `t ← (1−d)·p + d·Eᵀt` where `p` is the teleport vector concentrated on the trusted user (`d = 0.85`). Voters with no endorsements send their mass back to `p`.
4. Stop at an L1 change under 1e-6 or 100 iterations. Scale so the maximum is 1.

This always converges to a unique, non-negative distribution. It cannot be singular, so it never throws.

**NumPHP is dropped.** The power iteration is about 20 lines of plain PHP over sparse arrays and needs no linear algebra library. Removing `markrogoyski`/NumPHP from `composer.json` changes dependencies, so it is part of the sign-off.

## What Trust means and affects

Trust is a per-member number in `[0,1]`: how strongly the trusted user (default: the Party owner) and the people they trust endorse that member's requests. It is a **reputation signal, never a gate**.

Recommended effect (single, bounded): a **Score bonus on a member's new Requests**, `round(trust × max_bonus)`, with `max_bonus` a typed setting (default 3). Trusted members' requests rise slightly faster. Low-trust members lose nothing, and nobody is blocked or hidden. Display of the number is out of scope.

## Small and no-vote Parties

The mod is inert unless there is signal. Scores are all `0` (no effect) when any of these hold:

- no trusted user is set (and the Party has no owner to default to);
- fewer than 3 members;
- fewer than 5 qualifying endorsements in the window.

With signal, the trusted user always holds the largest mass through teleport, so if only the trusted user's own tracks have endorsements, everyone else stays near 0, which is the correct "no evidence" outcome. Degenerate input (empty matrix, all-zero rows, the trusted user not a member) returns zeros and logs at debug. It never throws. A failure in one Party's calculation must not affect others (slice 11 isolation).

## Cadence and window

A scheduled action recalculates per Party every 15 minutes (setting), over a rolling window (default 7 days, as today). The calculation is read-only apart from replacing that Party's stored scores in one transaction.

## Extension points needed

| Need | Slice | Required |
|---|---|---|
| Score modifier on new Requests (the effect) | 10 | Yes |
| Scheduled action, isolated per Party (recalculation) | 11 | Yes |
| Typed settings: trusted user, `max_bonus`, window, cadence | 8 | Yes (already a listed dependency) |
| Mod-owned storage for scores (replaces `parties.trustscore`, `parties.trusted_user_id`, `party_members.trustscore`, with a data migration) | 10 | Yes |
| Flagged Requests (e.g. trust auto-approving) | 12 | **No.** Out of scope for slice 17 |
| Decoration / Queue addition | 10 | No |

Slice 17's existing dependencies (16, 8, 11) therefore need **10 added**. Slice 17 is not expected to split.

## Decisions for sign-off

1. Adopt personalised PageRank and drop NumPHP (dependency removal).
2. Effect is a bounded Score bonus on new Requests (default max 3), not a gate.
3. Thresholds: 3 members and 5 endorsements, otherwise inert.
4. Trusted user defaults to the Party owner when unset.
5. Add slice 10 as a dependency of slice 17.
