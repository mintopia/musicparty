# Decision: Build and run on the mintopia/php base images

Status: accepted
Date: 2026-11-26

## Context

The app images were built `FROM dunglas/frankenphp:php8.5`, compiled grpc with `pie install` and installed opentelemetry, protobuf, redis, bcmath, pcntl and pdo_mysql on top, and carried a hand-written entrypoint (user creation, `setpriv`, `setcap -r`, `optimize`, `mods:sync`) and a copy of the stock Caddyfile. The grpc build took about 300s and failed in CI. The images at https://php.mintopia.net (`ghcr.io/mintopia/php`) ship those extensions prebuilt, handle `PUID`/`PGID`, `optimize` and Octane worker mode in their own entrypoint, and document how to run each Laravel role.

## Decision

All app images build from `ghcr.io/mintopia/php:8.5-*` (Alpine): `8.5-cli` for the composer and npm build stages, `8.5-frankenphp` for the runtime and the develop image. We follow the Usage page:

- Keep the image's entrypoint and Caddyfile. The command is no longer `octane:frankenphp`. Production sets `LARAVEL_OCTANE=true`, `LARAVEL_OPTIMIZE=true`, `PHP_OPCACHE_VALIDATE_TIMESTAMPS=0`, `PHP_MEMORY_LIMIT=512M`, `PUID`/`PGID`.
- Horizon, Reverb and the scheduler run the same image with a different `command`, healthcheck disabled (no Caddy admin API).
- Site-specific behaviour is added as `/docker-entrypoint.d/` hooks in `docker/hooks/`: `05-reverb-proxy` injects the ADR 0004 websocket proxy through `CADDY_SERVER_EXTRA_DIRECTIVES` (replacing our Caddyfile), `30-mods-sync` runs `mods:sync` for the web role, and the develop-only `01-app-owner` derives `PUID`/`PGID` from the bind-mounted checkout when unset.
- The develop image adds node, npm, composer, git and xdebug, and runs FrankenPHP in classic (non-worker) mode so edits apply without a restart. The `--watch` Octane command is dropped.
- `docker/Caddyfile`, `docker/entrypoint.sh` and the CI `caddyfile` job are removed; the websocket smoke test exercises the real configuration.

## Consequences

No extension is compiled in our builds. Image behaviour tracks the base image's documented contract, so upgrades are tag bumps. Production no longer runs `octane:frankenphp`, so Octane's `--watch`, `--workers` and `--max-requests` flags do not apply; use `LARAVEL_OCTANE_WORKERS`. We depend on the mintopia images' availability and release cadence; pin a digest for production if that matters. Xdebug is not in the base image and is still installed in the develop image.

## Supersedes

None
