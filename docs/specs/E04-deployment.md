# E4 Deployment

Serve the app and its websockets from one public hostname: Caddy in the app container routes websocket upgrades to Reverb.

- **Status:** Draft
- **Wave:** 4 (parallel with E5)
- **blocked_by:** E3

## Problem

- Reverb runs as a separate `reverb` service (`docker-compose.yml:23-33`, `reverb:start --port=80` in dev) and needs its own public hostname. The Caddyfile (`docker/Caddyfile`) has no `reverse_proxy` or websocket handling, so in production an external proxy outside the repo must route a second host to the `reverb` container on 8080.
- The browser is told where Reverb is through `window.pusherConfig`, built from `env('VITE_REVERB_APP_KEY'|'_HOST'|'_PORT'|'_SCHEME')` inline in `resources/views/layouts/app.blade.php:9-14`, `layouts/login.blade.php:13`, `parties/tv.blade.php:9` and `parties/ytplayer.blade.php:9`. Calling `env()` in views breaks once config is cached (`php artisan optimize` runs in `docker/production/entrypoint.sh`).
- `docker/production/production.env:24-35` mixes server-side (`REVERB_HOST=reverb`, `REVERB_PORT=8080`, scheme http) and browser-side (`VITE_REVERB_PORT=443`, scheme https) settings. `.env.example:52-57` still carries `VITE_PUSHER_*`.
- Production topology (app, reverb, horizon, scheduler) is not described in the repo; `docker-compose.yml` is dev-only. The local deployment needs two URLs (app and `-ws`).

## Goals

- One public hostname per instance for HTTP and websockets.
- The browser connects Echo to the page's own origin, with no host, port or scheme configuration.
- Reverb is never directly exposed to the internet.
- Dev compose, production setup, env files and README describe the same single-host topology.

## Non-goals

- Changing Reverb itself, channels or events (E5, E8).
- Encore connection design (E8); this epic only guarantees Encore's relay URL works on the app host.
- TLS termination inside the container; the Operator's reverse proxy still terminates TLS.
- Horizontal scaling of Reverb.

## Requirements

1. The Caddyfile matches websocket upgrade requests (`Connection` contains `Upgrade` and `Upgrade: websocket`) and reverse-proxies them to the Reverb service. Every other request goes to `php_server` as today.
2. The Reverb upstream host and port come from env (defaulting to the internal `reverb` service and its server port), so dev and production use the same Caddyfile.
3. Websocket proxying keeps Octane FrankenPHP worker mode, metrics, logging (including the `authorization` query redaction) and compression for non-websocket traffic unchanged.
4. Server-side publishing from the app to Reverb stays on the internal network (`REVERB_HOST`/`REVERB_PORT`/`REVERB_SCHEME` pointing at the `reverb` service).
5. `window.pusherConfig` (or its replacement) is built from config, not `env()`, and contains only the app key. Echo uses `window.location` for host, port and TLS, so it always connects to the page's origin.
6. `VITE_REVERB_HOST`, `VITE_REVERB_PORT`, `VITE_REVERB_SCHEME` and the stale `VITE_PUSHER_*` entries are removed from `.env.example`, `production.env` and compose files.
7. Reverb's allowed origins are restricted to the app's own host (from `APP_URL`).
8. The dev `docker-compose.yml` no longer publishes a Reverb port; the Traefik override (`docker-compose.override.traefik.yml`) routes only the app host. Dev and production use the same Reverb server port.
9. The repo documents the production topology: one image running as app, reverb, horizon and scheduler services, MariaDB, Redis, and the Operator's reverse proxy forwarding one hostname (with websocket upgrade headers) to the app container. This is shipped as an example production compose file plus a README section. The example production compose is a maintained, supported file, kept in step with the dev compose whenever services or env change.
10. The README states the Encore relay URL for an instance: `wss://<app host>/app/<REVERB_APP_KEY>`.
11. CI validates the Caddyfile (`frankenphp validate` or equivalent) so a broken routing config fails the PR.
12. An ADR in `docs/adr/` records the single-hostname decision and that the browser connects to its own origin.
13. `CLAUDE.md` and `AGENTS.md` describe the local deployment with a single URL. The workspace's `musicparty-ws.*` hostname is retired once E4 merges. The v2 deployment (`musicparty-old*`) is a separate checkout and is not changed.

## Acceptance criteria

- On the dev compose stack, a browser on the app URL connects to `wss://<app host>/app/<key>` and receives `party.{code}` and private-channel events; no request goes to a separate websocket host.
- The `reverb` container has no published port in dev or the example production setup.
- `php artisan optimize` followed by a page load renders a working `pusherConfig` (a Pest test asserts the key is present and no host/port/scheme is emitted).
- A non-upgrade request to `/app/<key>` is served by Laravel (404), not Reverb; an upgrade request reaches Reverb (verified manually or with a websocket smoke test in CI).
- The Web Player, TV page, YouTube player page and Queue all update live on the local deployment through the single host (manual check).
- Encore's Pusher relay connects through the app host (manual check, if an Encore device is available).
- CI validates the example production compose file (`docker compose config`).
- `.env.example` and `production.env` contain no `VITE_REVERB_HOST/PORT/SCHEME` or `VITE_PUSHER_*`.

## Functionality that must be retained

- All realtime behaviour: public `party.{code}` events, private Queue events, the private `spotifytoken` channel from E1, YouTube broadcasts to the TV page.
- Playback Webhooks (librespot, simple, soloist) on the app host.
- Horizon, Pulse and Prometheus metrics endpoints.
- The intentional `REVERB_APP_SECRET` in `production.env` stays as is.

## Suggested ticket slices

| # | Slice | blocked_by |
|---|---|---|
| 1 | Caddyfile websocket upgrade routing to Reverb, env-driven upstream, CI validation, ADR (req 1-4, 11, 12) | none |
| 2 | Same-origin Echo config from `config()`; remove `env()` from Blade and the `VITE_REVERB_*`/`VITE_PUSHER_*` vars; Reverb allowed origins (req 5-7) | 1 |
| 3 | Dev compose and Traefik override: single host, no Reverb port (req 8) | 1 |
| 4 | Maintained example production compose, its CI validation, and production env (req 9) | 1, 2 |
| 5 | README (topology, Encore relay URL), agent docs and `musicparty-ws.*` retirement (req 10, 13) | 2, 3, 4 |

Slice 2 waits for slice 1 so the app is never deployed with a browser pointing at an origin that cannot route websockets.

## Risks and open questions

Implementation-level; settle when tickets are written.

- **Operator proxies.** The Operator's reverse proxy must pass `Upgrade`/`Connection` headers and allow long-lived connections. Most do by default; the README must say so. Timeouts on idle websockets are proxy-specific.
- **Path collisions.** Routing on the upgrade header means the app can never serve its own websocket endpoint on another path. Acceptable today; revisit if Octane/FrankenPHP ever needs Mercure.
- **Reverb HTTP API.** Server-side publishing uses Reverb's HTTP endpoints on the internal network only; confirm nothing external needs them on the public host.
