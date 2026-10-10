# Leaky-bucket rate limit for search, on Redis

Search calls the Music Provider's catalogue API with the instance's client credentials, so one client hammering search can use up the Spotify quota for every Party. When Spotify returns 429, the shared backoff also blocks polling and enqueueing. Today only the `api` group has a limiter (180 a minute, fixed window). The web search on the Party page (`GET /parties/{code}/search?q=`) has none.

The limit should be generous, global and per user: one allowance per user that covers every Party and both web and API search. It should also forgive bursts, because people refine their search as they type, and then hold a steady rate. Laravel's `RateLimiter` and `throttle` middleware count in fixed windows (`decaySeconds`). A fixed window allows double the limit across a window boundary, and once a user is blocked they stay blocked until the window resets. Neither matches a smooth bucket. No first-party package offers a leaky bucket or GCRA.

Decision:
- Add a small leaky-bucket limiter, implemented as GCRA (the generic cell rate algorithm, which behaves like a leaky bucket used as a meter). It runs as one atomic Redis Lua script that stores a single "theoretical arrival time" per key, so it is correct across Octane workers without locks.
- The key is `search:{userId}`, shared by the web Party page search and `GET /api/v1/parties/{party}/search`.
- Defaults live in `config/musicparty.php`: a burst of 30 searches, draining at 1 per second (60 a minute sustained). Environment variables can override them.
- When a search is over the limit, the API returns 429 with `Retry-After`. The web page shows the search as temporarily unavailable, says when to retry, and still renders the rest of the page.
- All other limits (join, Requests, Votes, the `api` group) stay on Laravel's `RateLimiter`, which is first-party.

Consequences: about 40 lines of Lua and a PHP wrapper to own, tested against Redis with a frozen clock. Revisit if Laravel's `RateLimiter` gains a sliding or leaky strategy.
