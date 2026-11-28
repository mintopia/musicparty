# Party country and the per-member search/browse limit

A Party has a `country` (ISO 3166-1 alpha-2) that search and browse send as the provider market. The migration copies each existing Party's owner `users.market`, so existing search results do not change. The column defaults to `GB`, matching `users.market`, which is also the fallback when an owner's market is blank.

Each member's search and browse calls draw from one `LeakyBucket` keyed by Party and user. Defaults are a burst of 10 and a leak of 1 call per second, configured in `config/musicparty.php` (`MUSICPARTY_SEARCH_BURST`, `MUSICPARTY_SEARCH_LEAK_PER_SECOND`). Over the limit, the page renders the same "try again shortly" message as provider backoff and makes no provider call.

Provider search responses are cached for 60 seconds, keyed by Music Provider, Party country, query, type, page and page size. Only the provider response is cached; eligibility and badges are computed per viewer. A cache hit makes no provider call and does not draw from the member's bucket; only a miss does. Album and artist pages are not cached. The search, album and artist routes also sit behind a `throttle:search` route limit (120 requests per minute per user) as an outer guard, and queries are limited to 200 characters.
