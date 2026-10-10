# Queue broadcasts carry the state, and clients apply it

The realtime spec says payloads use the same shapes as API resources so a client can apply either. The phone page (`Party/Show.vue`) instead treats `QueueUpdatedEvent` as a poke: it throws the payload away and does a partial Inertia reload after a random delay. At the 500-Member target every Queue change becomes about 500 Octane requests, each recomputing vote sums, rating summaries and Decorations. The snapshot (`PartyQueueSnapshot`) and `QueueEntryResource` are built separately and have drifted: the snapshot has no `my_vote`, `my_rating` or `started_at`. The TV page already applies the payload directly.

Decision:
- One serializer per shape, in the context that owns it, builds both the API resource and the broadcast payload. A Queue entry is serialized once for the public channel and the API; the same code produces both.
- Public payloads carry only public state. Per-Member state (the Member's own Votes and Ratings) is sent on that Member's private channel and returned by the API for that Member. The client merges the two.
- Clients apply broadcast payloads directly. They fetch over HTTP only to resync: on first load, after a reconnect, when the page becomes visible again, or when a payload's version is newer than the client understands.
- This replaces the poke-then-reload approach and the debounce and jitter added for it.

Consequences: a Queue change costs one broadcast instead of one request per Member. Payload shapes are part of the contract, tested against the AsyncAPI document (ADR-0015). The member channel carries more traffic, one small event per Vote.
