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

Images are published to `ghcr.io/mintopia/musicparty` by `.github/workflows/publish-docker-images.yml` for `linux/amd64`
and `linux/arm64`. Tags: `latest` (master branch), `develop` (develop branch), `feature-v3-rewrite` (the v3 integration
branch) and a version tag for each `vX.Y.Z` release.

`example/docker-compose.yml` uses the `feature-v3-rewrite` tag until v3 is released; change it to `latest` or a version tag once there is one. It runs the web app, Horizon, the scheduler, Reverb, Redis and MariaDB from the image.

1. Copy `example/docker-compose.yml` somewhere, create `.env` and a `.env.mariadb` (`MYSQL_ROOT_PASSWORD`,
   `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD`), and `mkdir logs uploads`.
2. In `.env` set `APP_URL`, `APP_KEY` (run `docker compose run --rm artisan key:generate --show` after creating `.env` with an empty `APP_KEY=`), the `DB_*` values to
   match MariaDB, and the provider credentials. The image already sets production defaults for drivers, Redis and Reverb.
3. Set the public Reverb address that browsers will use: `REVERB_PUBLIC_HOST` to your hostname,
   `REVERB_PUBLIC_PORT=443` and `REVERB_PUBLIC_SCHEME=https`. Leave `REVERB_HOST/PORT/SCHEME` as the internal address
   (`reverb`, `8080`, `http`). They default from `APP_URL` when unset. Change the default `REVERB_APP_KEY` and `REVERB_APP_SECRET`.
4. Start it:

```bash
docker compose up -d --wait redis database
docker compose run --rm artisan migrate --force
docker compose run --rm artisan db:seed --force
docker compose up -d
```

Put a reverse proxy in front. `musicparty` listens on port 80 and `reverb` on port 8080 (`REVERB_SERVER_PORT`). Forward
`/app` (WebSocket connections) and `/apps` (Reverb's HTTP API) to Reverb and everything else to the app, for example
with Caddy:

```
musicparty.example.com {
  @reverb path /app /app/* /apps /apps/*
  reverse_proxy @reverb musicparty-reverb-1:8080
  reverse_proxy musicparty-musicparty-1
}
```

### Operations

- Back up the MariaDB data directory (or run `mysqldump`) and the `uploads` directory, which holds uploaded theme
  assets. Restore by loading the dump into a fresh database and putting `uploads` back.
- To upgrade, pull the new image, run `docker compose run --rm artisan migrate --force`, then `docker compose up -d`.
  Migrations are forward-only, so roll back by restoring the backup and the previous image tag.
- Queued jobs can run for up to 60 seconds, so give Horizon a stop grace period of at least that long
  (`stop_grace_period: 90s` on the `horizon` service) so a restart does not kill jobs part-way.

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

### Trusted proxies

`TRUSTED_PROXIES` is a comma-separated list of proxy addresses or CIDRs (for example `10.0.0.0/8,192.0.2.7`) whose
`X-Forwarded-*` headers the app honours. It defaults to `*`, which trusts any proxy. With the default, the client address
is taken from `X-Forwarded-For` whoever sends it, so the metrics IP allow-list (`PROMETHEUS_ALLOWED_IPS`) and IP-keyed
rate limits are only as strong as your network guarantee that clients reach the app only through the proxy. Where you
know the proxy's address, set `TRUSTED_PROXIES` to it.

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
