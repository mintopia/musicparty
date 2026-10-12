# Trust score and Whamageddon ship as first-party Mods

The archived v3 proposal listed built-in trust scores as removed and put the trust score (EigenKarma) Mod and the Whamageddon Mod out of scope. The mods spec requirement "Future Mods are non-goals but possible" said they must not be built (triage diff-1, diff-3, dec-3). ADR-0004 designed the extension points for exactly these. The owner ruled that both are restored as Mods.

v2 behaviour to carry over:
- Trust score (`Party::calculateTrustScores`, command `party:calculatetrust`): a per-Party trusted user anchor. It builds a matrix of positive Votes and positive Ratings that each Member gave each requester over the last 7 days, solves a linear system anchored on the trusted user, and stores a trust score per Member. In v2 it was computed and listed but not used for selection.
- Whamageddon (`app/Mods/Whamageddon`): hourly while the Party is active, it ensures a configured Track (default Wham!, "Last Christmas") is in the Queue as a system Request with a label and CSS classes. Until it has played, it adds a score adjustment (+1 by default) each hour. After it plays, it is re-added 2 hours later. It skips upvotes between 02:00 and 10:00, and from 23:00 sets `not_before` to 10:00 the next day. Enabling adds it and disabling removes it.

Decision:
- Both are first-party Mods and use only the ADR-0004 extension points.
- The Trust Score Mod is a Scheduled action that recalculates trust per Member. Settings: the anchor Member (default the Host) and the window in days (default 7). Trust is computed as personalised PageRank anchored on that Member, in-house, rather than v2's NumPHP linear solve, which needs a new dependency and can be singular.
- Trust is shown to the Host and Moderators on the Members pages, through a new `members.columns` slot of the existing build-time Vue slots extension point. An optional Score Modifier (setting, default off) weights a Request's Score by its requester's trust. There is no public Decoration of trust, because Decorations are public.
- The Whamageddon Mod is an hourly Scheduled action that creates the system Request, plus a Score Modifier for the hourly bonus, `not_before` eligibility for the overnight hold, and a Decoration in place of v2's CSS classes.
- No new extension point is added. If implementation finds one is needed, that needs its own ADR.

Consequences: the mods spec's non-goal requirement is narrowed to AI suggestion and Fallback generation. Trust is computed but only affects the Queue if a Host turns the Score Modifier on.
