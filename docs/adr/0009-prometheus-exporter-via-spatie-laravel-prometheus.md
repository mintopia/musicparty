# Prometheus metrics via spatie/laravel-prometheus, including Live Stats

Operators scrape Music Party with Prometheus. `spatie/laravel-prometheus` was already installed and wired at `/prometheus`, but none of its metrics worked. `MetricsCollector` (the middleware that counts requests by method and status into Redis) is not registered in `app/Http/Kernel.php`. Nothing increments `metrics.exceptions`. `PrometheusServiceProvider::registerHorizonCollectors()` is never called. The endpoint is open to anyone because `PROMETHEUS_ALLOWED_IPS` is empty, and nothing tests any of it.

House rules prefer first-party Laravel packages, and a non-first-party package needs an ADR. There is no first-party Prometheus exporter. Pulse is first-party but is a dashboard with its own storage, not a scrape endpoint in the Prometheus text format. Horizon exposes metrics only through its own UI and API. Writing our own exporter would repeat what the Spatie package already does: text-format rendering, Horizon collectors, and gauge and counter types with labels.

Decision:
- Keep `spatie/laravel-prometheus` as the exporter.
- Make every registered metric real. Register `MetricsCollector` in the HTTP kernel and count requests (by method, and by status code) as Prometheus counters. Count uncaught exceptions from the exception handler. Register the Horizon collectors. Counts live in Redis so every Octane worker and Horizon process adds to the same total.
- Export Live Stats as metrics. Values are read from the `party_stats` projection and a few cheap aggregate queries when Prometheus scrapes. Nothing is recomputed per scrape. Party-scoped series carry a `party` label (the party code) and cover only Live and Paused Parties, which keeps cardinality near the target of 25 Live Parties. Top-N lists (top Tracks, top requesters, most upvoted, most downvoted) carry a `rank` label (1 to 5) plus the display name, the same set of names the Stats page shows to Members. Counts across all Parties carry only a `state` label.
- Deny by default. The endpoint answers only a scraper that sends `PROMETHEUS_TOKEN` as a bearer token or comes from `PROMETHEUS_ALLOWED_IPS` (CIDR allowed). With neither configured it returns 403. A secret `PROMETHEUS_PATH` stays optional, but it is not the protection.
- Tests cover every metric family and the access rules.

Consequences: Top-N labels turn over as rankings change, which creates short-lived series. That is accepted while there are 5 ranks and about 25 Parties. The cost is that track titles and Member nicknames reach Prometheus storage. They are already public on the TV screen. Revisit this if a first-party exporter appears, or if per-Party series outgrow the scale target.
