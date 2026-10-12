# Flagged Requests are a core Request state, not owned by a mod

The AI review mod can return allow, flag or reject, and other mods (moderation rules, Trust) may want "hold this for a manager" too. We make the **Flagged Request** a core state with a single manager approval queue, rather than letting each mod invent its own holding area.

Flagging is a per-Party opt-in, because some Parties don't want the moderation overhead. When it is off, a flag lets the Request through.
