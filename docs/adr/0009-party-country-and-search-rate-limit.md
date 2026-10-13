# Party country and the per-member search/browse limit

A Party has a `country` (ISO 3166-1 alpha-2) that search and browse send as the provider market. The migration copies each existing Party's owner `users.market`, so existing search results do not change. The column defaults to `GB`, matching `users.market`, which is also the fallback when an owner's market is blank.

Each member's search and browse calls draw from one `LeakyBucket` keyed by Party and user. Defaults are a burst of 10 and a leak of 1 call per second, configured in `config/musicparty.php` (`MUSICPARTY_SEARCH_BURST`, `MUSICPARTY_SEARCH_LEAK_PER_SECOND`). Over the limit, the page renders the same "try again shortly" message as provider backoff and makes no provider call.
