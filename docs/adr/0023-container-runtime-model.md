# Container runtime: one image, one entrypoint, unprivileged, bind mounts

The published image runs every process as root, FrankenPHP listens on port 80 with Caddy's admin API on `0.0.0.0:2019`, and only the web role runs the entrypoint, so Horizon, the scheduler, Reverb and `artisan` start with uncached config. Migrations are manual, Redis has no persistence, nothing has a healthcheck, and Horizon is killed 10 seconds into a deploy. The cache defaults to `file`, although Reverb, the web workers and Horizon run in separate containers and coordinate through locks and buffers in the cache. A Traefik override in the repository is incomplete. The 2026-10-10 v3 review raised all of these.

Decision:
- One image serves every role. A single entrypoint takes the role as its command (`web`, `horizon`, `scheduler`, `reverb`, `migrate`, or any `artisan` arguments). Compose files never override the entrypoint.
- The entrypoint starts as root, creates or adjusts a user and group from `PUID` and `PGID` (default 1000), changes ownership of only the writable paths (`storage`, `bootstrap/cache`), caches config, routes, events and views, then drops to that user with `su-exec` before running the role. Nothing runs as root after start-up.
- Every listener uses an unprivileged port: FrankenPHP on 8080 in the web container, Reverb on 8080 in its own container, and Caddy's metrics on a separate site listener on 9180 that Compose does not publish. Caddy's admin API listens on `localhost:2019` only and is never published or reachable from other containers.
- Deploys run a one-shot `migrate` role (`php artisan migrate --force --isolated`). Web, Horizon and the scheduler wait for it to complete successfully. MariaDB and Redis have healthchecks, and dependants wait for them to be healthy.
- Horizon, Reverb and the web role get a stop grace period longer than their longest job or drain time.
- Persistent data uses bind mounts only, never named volumes: the database, Redis (append-only file on), `storage/app` and logs. Resource limits and database backups are documented for operators, not set in the shipped Compose file.
- The cache, session and queue default to Redis through the standard Laravel settings (`CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION`). Only the test suite uses other stores (ADR-0024).
- Images use the latest stable release of each base (PHP base, Node LTS, MariaDB LTS, Redis) in the Dockerfiles, Compose files and CI alike.
- The reverse proxy is the operator's. The repository ships no Traefik file; the README gives a Caddy example that forwards the app and the Reverb paths (`/app`, `/apps`).
- Baked production defaults in `production.env` stay, overridable from Compose (decided in the review as won't fix, devops-M8).

Consequences: operators set `PUID`/`PGID` to own their bind-mounted directories. Port numbers inside the container change, so reverse-proxy targets change on upgrade. The first deploy after upgrading runs migrations automatically.
