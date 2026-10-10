# One Spotify rate-limit backoff per instance

Spotify rate-limits by client application, not by Host account, over a rolling window. `SpotifyMusicProvider` keeps one backoff in the shared cache: a 429 stores the `Retry-After` deadline, and every Spotify call (search, playback polling, enqueue, playlist reads) checks it first and fails fast with `ProviderTemporaryFailure` until it passes. ADR-0010 mentions this in passing; nothing decided it.

We considered a backoff per Host account or per kind of call. Both keep calling an API that has already refused the application, which extends the penalty for every Party.

Decision:
- One backoff per instance, shared by every Spotify call and every Party, stored in the shared cache with the `Retry-After` value (default 5 seconds when the header is missing).
- Callers treat the failure as temporary. Search tells the Member when to retry, polling skips that cycle, and enqueue goes through the enqueue backoff without consuming a retry attempt.
- Playback ticks never call Spotify unless there is a hand-off to make (see the realtime and players specs), and playlist reads are cached, so routine work does not spend the budget.
- Soloist Players observe playback over Reverb (ADR-0003) rather than by polling, which is the main way to stay inside the limit.

Consequences: one noisy Party can pause Spotify calls for every Party until the deadline passes. That is preferable to repeated 429s, which Spotify answers with longer bans.
