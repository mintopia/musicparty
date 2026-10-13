# TV page soak check

Repeatable check that `/parties/{code}/tv` (Inertia page `Parties/Tv`) does not leak memory, timers or listeners over a long run and resyncs after a realtime outage.

## Procedure

1. Start the stack (app, Reverb, Horizon, Redis, MariaDB) and a party with an active queue and a playing track.
2. Launch headless Chromium with remote debugging and open the TV page as a guest:
   `chromium --headless --remote-debugging-port=9222 --enable-precise-memory-info https://<host>/parties/<CODE>/tv`
3. Via the DevTools protocol (Puppeteer/Playwright CDP session), every 5 minutes for at least 4 hours record:
   - `performance.memory.usedJSHeapSize` (after `HeapProfiler.collectGarbage`)
   - `Runtime.getHeapUsage` and DOM node count (`Memory.getDOMCounters`: `nodes`, `jsEventListeners`)
   - Active timer count: wrap `setInterval`/`clearInterval` with an `addInitScript` counter and read `window.__activeIntervals`.
4. Keep the party busy: skip tracks or let them roll over every few minutes so `Party\UpdatedEvent` pushes arrive continuously. Pause and resume playback several times.
5. Reverb outage: at the 1 hour mark stop Reverb for 60 seconds (`docker compose stop reverb`), change the playing track while it is down, then start Reverb. Within about 10 seconds of reconnection the page must show the new track (the page performs `router.reload({only: ['state']})` when the Pusher connection returns to `connected`). Repeat at hours 2 and 3.
6. Close the page and confirm the interval counter returns to 0.

## Pass criteria

- Used JS heap after GC grows by no more than 10% between hour 1 and the end of the run, with no monotonic upward trend.
- `jsEventListeners` and DOM node counts are flat (within noise) across samples.
- Active intervals stay at 1 while a track is playing and 0 while paused; never accumulates across track changes or pause/resume.
- Progress bar and clock advance after every pause/resume.
- After each Reverb outage the displayed state matches the server state without a manual refresh.
- No uncaught errors in the console, including when no track has ever been played (`current` is null).

The unit tests in `resources/js/tests/Tv.test.ts` cover the timer, cleanup, null-track and reconnect behaviour and a mount/unmount loop; this soak run covers the real browser and Reverb.
