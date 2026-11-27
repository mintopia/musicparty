# Encore is the source of truth for playback

Encore frames are applied as they arrive, with no ordering check. The last frame processed wins. This amends E08 requirements 36 and 37 for Encore.

**Why.** Encore frames carry two clocks. `track_changed` and `playback_changed` have no timestamp and are stamped with server time. `playback_state` and `position_sync` carry Soloist's `position.timestamp_ms`, which comes from the device clock and is an older sample time. When the two were compared, a skip's `track_changed` set `playback_sampled_at` to server now, and every later `playback_state` was dropped as stale. Each applied frame still refreshed `playback_received_at`, so polling stayed suppressed and the Party kept showing the wrong track.

**What changes.**
- `PlaybackSync::apply` skips the `sampledAt` ordering check for `source = Encore`. Other pushed sources (the Web Player) keep it, because each one stamps all its frames from a single clock.
- `position.timestamp_ms` is used only to anchor progress, and it is clamped to server now when it is in the future.
- While Encore is fresh, polling and forced playback do not run against it. This already follows from `hasFreshPushedPlayback()`. Because every Encore frame is now applied, being fresh means the latest frame was applied, so there is no separate reconciliation step.

**Trade-off.** If queue workers process two Encore frames out of order, the older one can briefly win. The next frame corrects it, and Soloist sends one about every 10 s while playing.
