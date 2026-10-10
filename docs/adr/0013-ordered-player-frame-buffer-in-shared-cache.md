# Player frames are ordered through a buffer in the shared cache

Design D5 planned one `HandlePlayerMessage` job per Player message. Horizon does not preserve order across workers, so a `position_sync` could overwrite a newer `track_changed`. `ProcessPlayerFrame` instead keeps an ordered buffer in the cache: the Reverb listener takes a sequence number with an atomic increment and stores the frame under it with a 300-second TTL, and a job holding a per-Party lock drains frames in sequence order from an "applied" cursor. Frames that expire or arrive out of order are skipped and only a discard counter records it. The 2026-10-10 v3 review found this was never recorded.

We considered a Redis list or stream per Party. It would be shorter, but it ties the pipeline to Redis commands that the test suite cannot run (ADR-0024), where the cache-backed buffer works with the array store in tests and the Redis store in production. We also rejected a single ordered worker per Party, which Horizon cannot express without a queue per Party.

Decision:
- Keep the cache-backed ordered buffer and the per-Party drain lock. The cache store must be shared by Reverb, the web workers and Horizon, which is why the cache defaults to Redis (ADR-0023).
- Every skipped frame is visible: it increments a Prometheus counter labelled by reason (`expired`, `out_of_order`) and writes a `player.frames_dropped` Party Log entry, at most once a minute per Party.

Consequences: ordering survives any number of Horizon workers. A frame lost to expiry is reported, not silent. Tests drive the buffer with the array cache.
