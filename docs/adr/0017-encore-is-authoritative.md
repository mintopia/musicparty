# Encore is the source of truth for playback

Encore frames are applied in the order the server received them, and are never compared by device timestamp. This amends E08 requirements 36 and 37 for Encore.

**Why.** Encore frames carry two clocks. `track_changed` and `playback_changed` have no timestamp and are stamped with server time. `playback_state` and `position_sync` carry Soloist's `position.timestamp_ms`, which comes from the device clock and is an older sample time. When the two were compared, a skip's `track_changed` set `playback_sampled_at` to server now, and every later `playback_state` was dropped as stale. Each applied frame still refreshed `playback_received_at`, so polling stayed suppressed and the Party kept showing the wrong track.

**What changes.**
- `PlaybackSync::apply` skips the `sampledAt` ordering check for `source = Encore`. Other pushed sources (the Web Player) keep it, because each one stamps all its frames from a single clock.
- `position.timestamp_ms` is used only to anchor progress, and it is clamped to server now when it is in the future.
- While Encore is fresh, polling and forced playback do not run against it. This already follows from `hasFreshPushedPlayback()`. Because every Encore frame is now applied, being fresh means the latest frame was applied, so there is no separate reconciliation step.

**Frames wait for the lock, and are ordered by receive time.**
- `ApplyPushedPlaybackState` is stamped with the server time, in milliseconds, when it is dispatched from Reverb.
- `PlaybackSync::apply` waits up to 15 s for the per-Party lock instead of giving up. On a timeout the job is released back to the queue, up to 4 attempts in total, and the loss is logged at warning only on the last one.
- The frame is mapped inside the lock, so a merge frame such as `position_sync` builds on the state applied just before it.
- A frame is skipped if a frame received later has already been applied. The last applied receive time is kept in a cache key per Party for 5 minutes. This is a single server clock, so it does not reintroduce the device-clock problem.
- Polling (`sync()`) stays non-blocking, because a skipped poll is harmless.
