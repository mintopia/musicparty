# Music Party

A collaborative party jukebox for LAN parties and other events. Guests log in with a social account, search for Tracks,
make Requests and Vote on the shared Queue. The Host's Player plays from it.

Terms such as Party, Host, Request, Queue, Up Next and Player are defined in [GLOSSARY.md](GLOSSARY.md).

## Features

- Social login with Discord, Twitch and Steam (account linking only), plus Spotify. Providers are configured by an
  admin and stored in the database.
- Parties with a join code, a TV screen with a QR code, and Party Roles: Host, Moderator, VIP and Guest.
- Search and Request Tracks, upvote and downvote Requests, and rate what is playing. The Queue is ordered by score and
  the winning Request is locked in as Up Next.
- Moderation: Pending Requests, Bans, a Blocklist and a Party Log.
- Fallback Playlist to top up a short Queue, and a History Playlist for played Tracks.
- Three kinds of Player: Polling, Browser and Soloist. A Party pairs one Music Provider with a compatible Player.
- Mods: optional per-Party extensions. The registered Mod is AI Request Review ([ADR-0004](docs/adr/0004-first-party-mod-system-with-extension-points.md)).
- Live Stats, and a versioned Party Export of an Ended Party for external reporting.
- Instance and Party Themes with light, dark or system Colour Scheme.
- A versioned REST API and Reverb channels, both documented with machine-readable specs.

## Architecture

- PHP 8.4, Laravel 13, Octane on FrankenPHP.
- Inertia with Vue 3, Vite and Tailwind CSS v4.
- MariaDB and Redis. Horizon runs queued work and a scheduler drives playback ticks, fallback checks and token refresh.
- Reverb provides realtime updates over the Pusher protocol, with Laravel Echo in the browser.
- Sanctum for API and Player Tokens. Pulse and Horizon dashboards are limited to admins.
- OpenTelemetry and Prometheus metrics are available for observability (see below).

Application code is split into bounded contexts under `app/Domain`: Admin, Identity, Mod, Music, Party, Playback,
Queue, Stats and Theming. Music Providers and Players are separate concepts
([ADR-0001](docs/adr/0001-separate-music-provider-and-player.md)).

## Local development

Requires Docker with Compose. The commands below use the helper services defined in `docker-compose.yaml`.

```bash
cp .env.example .env
docker compose run --rm composer install
docker compose run --rm artisan key:generate
docker compose up -d --wait db redis
docker compose run --rm artisan migrate
docker compose run --rm artisan db:seed
docker compose run --rm npm install
docker compose run --rm npm run build
docker compose up -d
```

The development compose file does not publish any ports. Create a `docker-compose.override.yaml` (loaded
automatically) to reach the app and Reverb from your browser:

```yaml
services:
  musicparty:
    ports:
      - "8000:80"
  reverb:
    ports:
      - "8080:8080"
```

Then set these in `.env` and restart the containers. `REVERB_HOST/PORT/SCHEME` are the internal address the app
publishes to. `REVERB_PUBLIC_HOST/PORT/SCHEME` are what the browser connects to, so they must be values your browser
can reach.

```dotenv
APP_URL=http://localhost:8000
BROADCAST_DRIVER=reverb
QUEUE_CONNECTION=redis
REVERB_APP_ID=musicparty
REVERB_APP_KEY=musicparty
REVERB_APP_SECRET=secret
REVERB_HOST=reverb
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_PUBLIC_HOST=localhost
REVERB_PUBLIC_PORT=8080
REVERB_PUBLIC_SCHEME=http
```

For hot reloading, run `docker compose --profile vite up vite` instead of `npm run build`.

Other helpers: `docker compose run --rm shell` opens a shell, and `docker compose run --rm artisan <command>` runs
Artisan.

## Login providers and Spotify

Provider credentials live in the database and are edited by an admin under `/admin/providers`. On a fresh install you
can seed them from environment variables instead:

```dotenv
DISCORD_CLIENT_ID=
DISCORD_CLIENT_SECRET=
TWITCH_CLIENT_ID=
TWITCH_CLIENT_SECRET=
STEAM_API_KEY=
SPOTIFY_CLIENT_ID=
SPOTIFY_CLIENT_SECRET=
```

```bash
docker compose run --rm artisan providers:seed
```

`providers:seed` creates each provider, fills settings that are still empty from config, and enables a new provider
only when all of its required settings are present. It never overwrites stored values, so it is safe to re-run.

Register these redirect URLs with each OAuth2 application, replacing the host with your `APP_URL`:

- Login: `/login/{provider}/return`, for example `/login/discord/return`
- Spotify account linking: `/spotify/link/return`

Spotify is the Music Provider. The Host links their Spotify account to a Party from the Party's settings, and the
Spotify application needs the second redirect URL above. Set `SPOTIFY_MARKET` to change the catalogue market (default `US`).

### First admin

There is no automatic admin. Log in once, then grant the `admin` role from the console (replace the user id):

```bash
docker compose run --rm artisan tinker --execute="App\Domain\Identity\Models\User::find(1)->roles()->attach(App\Domain\Admin\Models\Role::whereCode('admin')->first())"
```

Admins can then grant roles to other users in the admin area, issue Integration Tokens and edit site settings and the
Instance Theme. Integration Tokens can only be issued by admins ([ADR-0005](docs/adr/0005-admin-issued-integration-tokens-only.md)).

## Connecting a Player

Each Party has one Player, chosen by its Host in the Party settings.

- **Browser Player**: opens at `/parties/{code}/player` and plays audio in the Host's browser tab.
- **Polling Player**: Music Party asks Spotify what is playing on the Host's account. Polling intervals are set with
  the `MUSICPARTY_POLL_*` variables. A Webhook can trigger an immediate check but cannot control playback.
- **Soloist Player**: a Soloist proxy connects in to Reverb and pushes playback events. The
  Host issues a Player Token for the Party (`POST /api/v1/parties/{party}/player-tokens`), and the relay uses it to
  join the private `player.{code}` channel. Tokens are revocable by the Host. See
  [ADR-0003](docs/adr/0003-soloist-connects-via-reverb-pusher-protocol.md).

Soloist stale and disconnect timeouts are `MUSICPARTY_SOLOIST_STALE_AFTER` and `MUSICPARTY_SOLOIST_DISCONNECT_AFTER`.

## Testing and quality

Run these on the host, or through the `composer` service (`docker compose run --rm composer <script>`):

```bash
composer test          # Pest, parallel
composer test:coverage # Pest with coverage (needs xdebug)
composer analyse       # PHPStan level 8
composer lint          # Pint (check only)
composer rector        # Rector (dry run)
composer ci            # lint, analyse, rector, test:coverage
npm test               # Vitest for the Vue code
php artisan scramble:export --path=openapi/openapi.json   # regenerate the REST API spec
```

CI (`.github/workflows/ci.yml`) runs Pint, PHPStan, Rector, Pest with coverage and the architecture, OpenAPI and
AsyncAPI tests. Run `vendor/bin/pint` to fix formatting.

## Production deployment

### Requirements

- A Linux host with Docker and Compose v2 (`docker compose`), and a hostname with TLS terminated by a reverse proxy you run.
- About 2 GB of memory for the whole stack at small scale. See [Resource limits](#resource-limits) for per-service figures.
- Outbound HTTPS to the Music Provider and login providers.
- OAuth2 applications for the login providers and Spotify (see [Login providers and Spotify](#login-providers-and-spotify)).

Images are published to `ghcr.io/mintopia/musicparty` by `.github/workflows/publish-docker-images.yml` for `linux/amd64`
and `linux/arm64`. Tags: `latest` (master branch), `develop` (develop branch), `feature-v3-rewrite` (the v3 integration
branch) and a version tag for each `vX.Y.Z` release. `example/docker-compose.yml` uses the `feature-v3-rewrite` tag until
v3 is released; change it to `latest` or a version tag once there is one.

One image serves every role. Each service in `example/docker-compose.yml` passes the role as its command: `web`,
`horizon`, `scheduler`, `reverb` or `migrate` ([ADR-0023](docs/adr/0023-container-runtime-model.md)). Never override the
entrypoint.

### First deploy

1. Copy `example/docker-compose.yml` to an empty directory on the host and create the bind-mount directories next to it.
   Persistent data uses bind mounts only, never named volumes:

   ```bash
   mkdir -p database redis logs storage/app
   ```

2. Choose the user the app runs as. The entrypoint starts as root, creates a user from `PUID` and `PGID` (default
   1000), takes ownership of `storage` and `bootstrap/cache` inside the container, then drops to that user. Nothing runs
   as root afterwards. The bind-mounted `logs` and `storage/app` directories must be writable by that user, so either
   set `PUID`/`PGID` in `.env` to the owner of the directories (`id -u` and `id -g`), or `chown` them to 1000:1000.
   MariaDB and Redis manage the ownership of `database` and `redis` themselves.

3. Create `.env.mariadb` for the database container:

   ```dotenv
   MARIADB_ROOT_PASSWORD=change-me-root
   MARIADB_DATABASE=musicparty
   MARIADB_USER=musicparty
   MARIADB_PASSWORD=change-me
   ```

4. Create `.env` with at least the settings below. The image already bakes in production defaults for drivers (Redis for
   cache, session and queue), Redis and the internal Reverb address, so do not repeat them.

   ```dotenv
   APP_URL=https://musicparty.example.com
   APP_KEY=
   PUID=1000
   PGID=1000

   DB_DATABASE=musicparty
   DB_USERNAME=musicparty
   DB_PASSWORD=change-me

   REVERB_APP_KEY=generate-a-random-key
   REVERB_APP_SECRET=generate-a-random-secret
   REVERB_PUBLIC_HOST=musicparty.example.com
   REVERB_PUBLIC_PORT=443
   REVERB_PUBLIC_SCHEME=https
   ```

   `DB_PASSWORD` must match `MARIADB_PASSWORD`. Generate `APP_KEY` with
   `docker compose run --rm artisan key:generate --show` and paste the result in (the command needs the `APP_KEY=` line
   to exist, even if empty). `REVERB_APP_KEY` and `REVERB_APP_SECRET` have insecure defaults baked into the image, so
   always set your own. `REVERB_HOST`, `REVERB_PORT` and `REVERB_SCHEME` are the internal address the app publishes to
   (`reverb`, `8080`, `http`); leave them alone. `REVERB_PUBLIC_*` is what browsers connect to, so it must be the public
   hostname, port 443 and `https` behind your proxy. Add the provider credentials, or set them later in the admin area
   (see [Login providers and Spotify](#login-providers-and-spotify)).

5. Start the stack and wait for it to become healthy:

   ```bash
   docker compose up -d --wait
   ```

   Compose runs the one-shot `migrate` service first (`php artisan migrate --force --isolated`). Web, Horizon and the
   scheduler wait for it to finish successfully, and MariaDB and Redis must be healthy before anything starts. If
   `migrate` fails, nothing else starts: read its output with `docker compose logs migrate`. To seed login providers
   from the environment, run `docker compose run --rm artisan providers:seed`.

6. Put the reverse proxy in front (next section), log in once, and [grant the first admin](#first-admin).

### Ports

Nothing is published to the host by the example Compose file. Inside the Compose network:

| Service | Port | Purpose |
|---|---|---|
| `web` | 8080 | The app, with a `/api/v1/ping` healthcheck |
| `reverb` | 8080 | WebSocket connections (`/app`) and Reverb's HTTP API (`/apps`) |
| `web` | 9180 | Caddy server metrics. Never publish it |
| `database` | 3306 | MariaDB |
| `redis` | 6379 | Redis |

Caddy's admin API listens on `localhost:2019` inside the web container only. It is not reachable from other containers
and must never be published. All ports are unprivileged, so the containers never need `NET_BIND_SERVICE`.

### Reverse proxy

The reverse proxy is yours to run; the repository ships no Traefik or other proxy file. It must:

- Terminate TLS for your `APP_URL` hostname.
- Forward `/app` and `/app/*` (WebSocket connections) and `/apps` and `/apps/*` (Reverb's HTTP API) to `reverb` on port
  8080, with WebSocket upgrades allowed and no short idle timeout on them.
- Forward everything else to `web` on port 8080.
- Set `X-Forwarded-For`, `X-Forwarded-Proto` and `X-Forwarded-Host` (Caddy does this by default).

If the proxy runs on the same host, publish the two ports on the loopback interface only, by adding this to
`docker-compose.override.yaml`:

```yaml
services:
  web:
    ports:
      - "127.0.0.1:8080:8080"
  reverb:
    ports:
      - "127.0.0.1:8081:8080"
```

Then a Caddyfile that forwards the Reverb paths looks like this:

```
musicparty.example.com {
  @reverb path /app /app/* /apps /apps/*
  reverse_proxy @reverb 127.0.0.1:8081
  reverse_proxy 127.0.0.1:8080
}
```

If Caddy runs in the same Compose project instead, use the service names and ports: `reverb:8080` and `web:8080`.
Because the internal port numbers changed in v3, update your proxy targets when upgrading from an earlier build.

### Trusted proxies

`TRUSTED_PROXIES` is a comma-separated list of proxy addresses or CIDRs (for example `10.0.0.0/8,192.0.2.7`) whose
`X-Forwarded-*` headers the app honours. It defaults to `*`, which trusts any proxy. With the default, the client address
is taken from `X-Forwarded-For` whoever sends it, so the metrics IP allow-list (`PROMETHEUS_ALLOWED_IPS`) and IP-keyed
rate limits are only as strong as your network guarantee that clients reach the app only through the proxy. A client that
can reach `web` directly can send any `X-Forwarded-For` it likes, which lets it pass the allow-list and dodge IP rate
limits. Where you know the proxy's address, set `TRUSTED_PROXIES` to it. With the loopback publishing above and a Docker
bridge network, requests arrive from the bridge gateway address (commonly `172.16.0.0/12`); check
`docker network inspect` and use the value you see. Prefer the bearer token (`PROMETHEUS_TOKEN`) over the IP allow-list
for metrics access.

### Resource limits

The example Compose file sets no resource limits. Add them per service in `docker-compose.override.yaml` using
`mem_limit`, `cpus` and `pids_limit`. These starting values come from the load test in
[docs/research/load-test-scale-target.md](docs/research/load-test-scale-target.md) (25 Live Parties of 500 Members, about
1,000 connections); measure with `docker stats` and adjust.

```yaml
services:
  web:
    mem_limit: 1g
    cpus: 2
    pids_limit: 512
  horizon:
    mem_limit: 1g
    cpus: 2
    pids_limit: 512
  scheduler:
    mem_limit: 256m
    cpus: 0.5
    pids_limit: 128
  reverb:
    mem_limit: 512m
    cpus: 1
    pids_limit: 256
  migrate:
    mem_limit: 512m
    cpus: 1
    pids_limit: 128
  database:
    mem_limit: 1g
    cpus: 2
    pids_limit: 512
  redis:
    mem_limit: 256m
    cpus: 1
    pids_limit: 128
```

A single Reverb process tops out just under 1,000 connections. For more, run a second Reverb service (Redis scaling, `REVERB_SCALING_ENABLED`,
is on by default) and have the proxy split `/app` between them. Keep the `nofile` ulimit of 65535 that
the example sets on `reverb`. Do not set `pids_limit` so low that Horizon cannot start its workers.

### Backup and restore

Back up the database, the `storage/app` directory (uploaded theme assets) and your `.env` files. Redis holds cache,
sessions, queue and counters; it runs with an append-only file in `./redis`, so a restart keeps its data, but nothing in it
is the source of truth, so you can lose it without losing Parties, Requests or Members.

Back up with `mariadb-dump` from inside the database container:

```bash
docker compose exec -T database sh -c 'mariadb-dump --single-transaction --routines --user=root --password="$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"' | gzip > musicparty-$(date +%F).sql.gz
tar czf musicparty-storage-$(date +%F).tar.gz storage/app
```

Restore into an empty database:

```bash
docker compose stop web horizon scheduler reverb
docker compose up -d --wait database
gunzip -c musicparty-2026-01-01.sql.gz | docker compose exec -T database sh -c 'mariadb --user=root --password="$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"'
tar xzf musicparty-storage-2026-01-01.tar.gz
docker compose up -d --wait
```

To restore onto a fresh host, copy `.env`, `.env.mariadb` and the compose file, create the directories, and load the dump
before the first `docker compose up`, with only the `database` service started.

### Upgrading

Always take a backup first. Migrations are forward-only, so you roll back by restoring the backup and the previous image
tag. To upgrade, pull the new image and recreate the stack; the `migrate` service runs on every `up`:

```bash
docker compose pull
docker compose up -d --wait
```

Horizon needs a stop grace period of at least 75 seconds and the example sets it, so a restart does not kill jobs
part-way. Keep it if you write your own Compose file.

**From v2.** v3 is a rewrite and runs on a fresh stack.

1. Back up the v2 database and uploads.
2. Deploy v3 following [First deploy](#first-deploy), pointing `DB_*` at the existing database. The `migrate` service
   converts the schema automatically on first start.
3. Re-enter the social provider credentials under `/admin/providers` (or run `providers:seed`), because they are now stored
   in the database, and re-register the redirect URLs listed above.
4. Reissue every Integration Token from the admin area. Old tokens do not carry over.
5. Update your proxy to forward `/app` and `/apps` to Reverb.

**From an earlier v3 build.**

1. Back up, then pull the new image.
2. Update your Compose file from `example/docker-compose.yml`: web, Horizon and Reverb now listen on unprivileged ports
   (8080), roles are passed as the command, and `migrate` is a one-shot service. Update proxy targets to the new ports,
   and set `PUID`/`PGID` so the bind-mounted directories are writable.
3. Reissue Integration Tokens, and Player Tokens for Soloist Players.
4. Run `docker compose up -d --wait`.

## Configuration

Behaviour tuning is in `config/musicparty.php`. Useful variables:

- `MUSICPARTY_FALLBACK_MINIMUM_QUEUE` is the Queue length below which the Fallback Playlist tops up (default 5).
- `MUSICPARTY_JIT_LEAD_SECONDS` is how long before the end of a Track a just-in-time Player is fed Up Next (default 15).
- `AI_REVIEW_OPENAI_MODEL` and `AI_REVIEW_JEV_MODEL` choose the models for the AI Request Review Mod.

## Observability

OpenTelemetry is off by default (`OTEL_PHP_AUTOLOAD_ENABLED=false`) and the repository ships no collector config or
backend credentials ([ADR-0016](docs/adr/0016-opentelemetry-via-operator-run-collector.md)). To enable it, run an
OpenTelemetry collector yourself (a sidecar container or a separate process) and point the app at it with the standard
exporter variables:

```dotenv
OTEL_PHP_AUTOLOAD_ENABLED=true
OTEL_SERVICE_NAME=musicparty
OTEL_EXPORTER_OTLP_ENDPOINT=http://collector:4317
OTEL_EXPORTER_OTLP_PROTOCOL=grpc
OTEL_EXPORTER_OTLP_HEADERS=
```

The collector needs an OTLP receiver on that endpoint and an exporter for your backend; keep the backend credentials in
the collector's own config, never in this repository or the app's environment. `OTEL_PHP_EXCLUDED_URLS` defaults to
`pulse,horizon/.*,api/v1/ping,_ignition/.*,_debugbar/.*` to skip noisy URLs.

To collect metrics too, add a Prometheus receiver to the collector that scrapes `/prometheus` (or your `PROMETHEUS_PATH`)
with `PROMETHEUS_TOKEN` as a bearer token.

Prometheus metrics are served by `spatie/laravel-prometheus` at `PROMETHEUS_PATH` (default `/prometheus`). The endpoint
returns 403 unless the scraper sends `PROMETHEUS_TOKEN` as a bearer token or connects from an address in
`PROMETHEUS_ALLOWED_IPS`, so set one of them before pointing a scraper at it ([ADR-0009](docs/adr/0009-prometheus-exporter-via-spatie-laravel-prometheus.md)).

A Prometheus scrape job for the endpoint through your proxy:

```yaml
scrape_configs:
  - job_name: musicparty
    scheme: https
    metrics_path: /prometheus
    authorization:
      credentials: the-value-of-PROMETHEUS_TOKEN
    static_configs:
      - targets: ["musicparty.example.com"]
```

Pulse (`/pulse`) and Horizon (`/horizon`) are dashboards for admins. Set `PULSE_ENABLED=false` on the `reverb` service, because a slow database stalls Reverb's single event loop. Caddy's own server metrics are on port 9180 inside the
`web` container and are not published; scrape them from another container on the Compose network if you need them.
Trusted proxy handling affects the IP allow-list: see [Trusted proxies](#trusted-proxies).

## Documentation

- [GLOSSARY.md](GLOSSARY.md): domain language
- [docs/adr/](docs/adr): architecture decision records
- [openspec/specs/](openspec/specs): living specifications for each capability
- [openapi/openapi.json](openapi/openapi.json): REST API, generated by Scramble with `php artisan scramble:export --path=openapi/openapi.json` (run against a migrated database with the default config); the docs UI is at `/docs/api` and the JSON at `/docs/api.json`, open in the local environment and restricted to admins elsewhere
- [asyncapi/asyncapi.json](asyncapi/asyncapi.json): Reverb channels and events
- [docs/research/](docs/research): load test and scoring research
- [docs/design/v2-reference/](docs/design/v2-reference): v2 screenshots used as the visual reference for v3

## Contributing

Pull requests are welcome. Run `composer ci` before opening one. Follow the language in GLOSSARY.md and record
architectural decisions as an ADR in `docs/adr/`.

## Thanks

This would not exist without the support of the following:

- UK LAN Techs
- Moogle

## License

The MIT License (MIT)

Copyright (c) 2024 Jessica Smith

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
