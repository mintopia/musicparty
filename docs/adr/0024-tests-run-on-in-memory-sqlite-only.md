# Tests run on in-memory SQLite with no other services

`phpunit.xml` overrides the cache, queue, session and broadcast drivers but not the database, so `php artisan test` from a dev checkout runs `RefreshDatabase` against the database named in `.env` and wipes it. CI runs the suite against MariaDB and Redis service containers, and two tests talk to Redis directly.

Decision:
- Every test runs on SQLite in memory. `phpunit.xml` sets `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:`, and the base test case refuses to run against any other connection.
- No test needs MariaDB, Redis, Reverb, Spotify or any other service, locally or in CI. Cache, session and queue use the array and sync drivers, broadcasting uses fakes, and external APIs use `Http::fake`, the Fake Music Provider and the Fake Player.
- Code that needs Redis-specific behaviour sits behind an interface with a Redis implementation and an in-memory one. Tests use the in-memory one. Tests that can only exercise the real Redis implementation are in an opt-in `redis` group, excluded by default and in CI, and run by hand.
- Because tests run on SQLite and production runs on MariaDB, migrations and queries use the schema builder and query builder only. Any driver-specific SQL needs a branch for both drivers and a test on SQLite.

Consequences: the suite cannot touch real data and runs anywhere PHP runs. MariaDB-only behaviour (locking, collation, JSON functions) is not exercised by tests; the deploy-time migration on MariaDB and the documented upgrade path are the check there.
