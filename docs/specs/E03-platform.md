# E3 Platform

Move to PHP 8.5, the latest stable Laravel and first-party packages, the streamlined Laravel skeleton, and an in-repo FrankenPHP image, without changing behaviour.

- **Status:** Draft
- **Wave:** 3
- **blocked_by:** E2

## Problem

- **Runtime:** `composer.json` requires `php ^8.2`; images build `FROM ghcr.io/mintopia/php-frankenphp-base:8.4.10` (`docker/develop/Dockerfile`, `docker/production/Dockerfile`), a shared base-image repo we would otherwise wait on.
- **Framework:** `laravel/framework` v12.20.0. `laravel/reverb` is pinned `@beta` with `"minimum-stability": "beta"` (`composer.json:15`, `:95`). Pest is v3.8.2.
- **Skeleton:** Laravel 10 structure. `bootstrap/app.php` is the old `new Application` form binding `App\Http\Kernel`, `App\Console\Kernel` and `App\Exceptions\Handler`. Middleware groups (including `webhooks`) live in `app/Http/Kernel.php`, the schedule in `app/Console/Kernel.php:17-33`, providers in `config/app.php:164-177` (including the hand-registered `WhammageddonServiceProvider` at `:164`). `.env` uses pre-11 names (`BROADCAST_DRIVER`, `CACHE_DRIVER`). `config/websockets.php` is a leftover next to `config/reverb.php`.
- **Telescope:** in production `require` (`composer.json:14`), with `TelescopeServiceProvider`, `config/telescope.php`, its migration (`2018_08_08_100000_create_telescope_entries_table.php`), `telescope:prune` in the schedule (`Console/Kernel.php:20`) and references in `config/reverb.php:52`, `config/pulse.php:138`, `phpunit.xml`. It records request payloads, including tokens.
- **Frontend tooling:** Vite 4.5.0, laravel-vite-plugin 0.8.1, @vitejs/plugin-vue 4.6.2, vue 3.4.18, laravel-echo 1.16.1, pusher-js 8.4.0-rc2 (a release candidate).
- **Spotify SDK:** `jwilsson/spotify-web-api-php ^6`. Branch `origin/feature/spotify-api-changes` (5 ahead, 3 behind `develop`) bumps it to `^7.1` "to hopefully handle Spotify API changes" and removes the ISRC Blocklist type (`PartyModerationType::mtISRC`, migration `2026_02_27_115427_remove_isrc_moderations.php`), because Spotify dropped ISRC from search results. It is untested and its `composer.lock` will conflict.

## Goals

- PHP 8.5 everywhere, from an image we build in this repo.
- Latest stable Laravel, first-party packages, Pest, Vite and npm dependencies; no beta or RC pins.
- The streamlined skeleton: `bootstrap/app.php` configuration, no HTTP/Console Kernel, no Exceptions Handler.
- Telescope gone; Pulse and Horizon kept; Debugbar dev-only.
- `feature/spotify-api-changes` taken in and verified.

## Non-goals

- Tabler or any UI change (E7). `@tabler/core` stays at its current version here.
- Caddy websocket routing and single-host deployment (E4).
- Behaviour fixes (E5) and refactors (E6).
- Publishing the base image to a shared base-image repo.

## Requirements

### Image and runtime
1. The PHP 8.5 FrankenPHP base is a build stage inside this repo's Dockerfiles, not a separately published image. It has every PHP extension the app uses today (at least those needed by MariaDB, Redis, Octane/FrankenPHP worker mode, intl, zip, bcmath, pcntl, OPcache, and the OpenTelemetry gRPC transport). The extension list is taken from `php -m` on the current base image.
2. `docker/develop/Dockerfile` and `docker/production/Dockerfile` build on that base. Dev keeps xdebug, Node and Composer; production keeps the 3-stage build.
3. `composer.json` requires `php ^8.5`. Octane keeps FrankenPHP worker mode with the current Caddyfile.
4. The image build runs in CI so a broken base fails the PR.

### Framework and packages
5. `laravel/framework` and every first-party package (Horizon, Octane, Pulse, Reverb, Sanctum, Socialite, Tinker, Prompts, Pint, Sail) are on their latest stable release. Reverb has no `@beta` pin and `minimum-stability` is `stable`.
6. Pest and its plugins, PHPUnit, Larastan and Rector are on their latest stable versions compatible with the above.
7. Third-party packages are upgraded to versions that support PHP 8.5 and the new Laravel. Any that cannot be upgraded are listed in the PR with the reason.

### Skeleton
8. `bootstrap/app.php` uses `Application::configure()` with `withRouting` (web, api, channels, console, and the `webhooks` routes with their middleware group), `withMiddleware` (every current global, group and alias entry, the CSRF exceptions that survive E1, `TrustProxies`), `withExceptions` (current `Handler` behaviour) and `withSchedule` or `routes/console.php` for the schedule.
9. `app/Http/Kernel.php`, `app/Console/Kernel.php` and `app/Exceptions/Handler.php` are deleted. Providers move to `bootstrap/providers.php`, including the Whamageddon provider.
10. Env names follow the current skeleton (`BROADCAST_CONNECTION`, `CACHE_STORE`, etc.) in `.env.example`, `docker/production/production.env` and the compose files. Config files are aligned with the new framework defaults while keeping every app-specific value.
11. `config/websockets.php` and any other dead config are removed.

### Telescope and dev tooling
12. Telescope is removed: package, provider, config, `telescope:prune` schedule, config references and the `TELESCOPE_ENABLED` test variable. A new migration drops the Telescope tables.
13. Debugbar and ide-helper stay in `require-dev` and are never loaded in production.
14. Pulse and Horizon, and their admin gates, keep working.

### Frontend tooling
15. Vite, laravel-vite-plugin, @vitejs/plugin-vue, vue, laravel-echo (v2), pusher-js (stable), axios, sass and the other npm dependencies are on their latest stable versions. `resources/js/bootstrap.js` is updated for any Echo API change.
16. `npm run dev` and `npm run build` work, including the dev compose `vite` service.

### Spotify SDK branch
17. `feature/spotify-api-changes` is brought onto the epic branch by merging (not cherry-picking archive code), with `composer.lock` regenerated.
18. The SDK v7 bump is verified: E2's Spotify fixtures are updated to v7 shapes and every characterization test passes. Any behaviour change is called out.
19. ISRC removal is applied: `mtISRC` is gone from the enum, model, Blocklist form and `SearchResult.vue`, and the migration deletes existing ISRC rules. E2 tests for ISRC rules are removed with the feature.
20. The branch's change to `.github/workflows/publish-docker-images.yml` is reconciled with E2's CI workflow.

### Docs
21. The Laravel Boost guidelines in `CLAUDE.md`/`AGENTS.md` are regenerated for the new versions, and the "Laravel 10 structure" guidance is replaced by the streamlined structure.

### Static analysis
22. Before any PHPStan baseline regeneration, slice 5 measures how many new errors the upgrade surfaces (by file and by rule) and reports the delta to the user. The user decides whether the baseline is regenerated or the errors are fixed; nothing is regenerated before that decision.

## Acceptance criteria

- The full Pest suite, Pint, PHPStan (baseline handled per req 22) and Rector pass in CI on PHP 8.5.
- `composer show` lists no beta/RC first-party packages; `npm ls` shows no RC packages.
- `app/Http/Kernel.php`, `app/Console/Kernel.php`, `app/Exceptions/Handler.php`, `config/telescope.php` and `config/websockets.php` do not exist.
- `php artisan schedule:list` shows the same entries as before minus `telescope:prune`.
- `php artisan route:list` shows the same routes and middleware as before.
- The PHPStan error delta is reported to the user and their decision is recorded on the slice 5 ticket.
- The local deployment runs on the new image: login, search, request, vote, Web Player, TV and YouTube pages, Horizon, Pulse and admin pages all work (manual smoke check).

## Functionality that must be retained

Everything on the baseline except Telescope and the ISRC Blocklist type, which are removed by decision. Routes, middleware, schedule, broadcasting, queues and Octane worker mode behave as before.

## Suggested ticket slices

| # | Slice | blocked_by |
|---|---|---|
| 1 | Merge `feature/spotify-api-changes`, verify SDK v7, ISRC removal (req 17-20) | none |
| 2 | Remove Telescope (req 12-14) | none |
| 3 | PHP 8.5 FrankenPHP base as a build stage; dev and production Dockerfiles; `php ^8.5`; image build in CI (req 1-4) | none |
| 4 | Streamlined skeleton migration and env renames (req 8-11) | 2 |
| 5 | Latest stable Laravel, first-party packages, Pest/Larastan/Rector; third-party bumps; PHPStan delta report (req 5-7, 22) | 1, 3, 4 |
| 6 | npm dependency upgrades and Echo v2 (req 15, 16) | none |
| 7 | Regenerate Boost guidelines and update agent docs (req 21) | 5 |

Slices 1, 2, 3 and 6 can run in parallel; each touches `composer.lock` or `package-lock.json`, so whichever lands second rebases and regenerates the lock file.

## Risks and open questions

Implementation-level; settle when tickets are written.

- **Latest stable versions.** The exact target versions are whatever is latest stable when slice 5 starts; record them in the PR.
- **PHP 8.5 extension support.** gRPC/protobuf and any PECL extensions must build on 8.5; if one does not, the OpenTelemetry transport may need to change. Raise rather than drop silently.
- **SDK v7 untested upstream.** If v7 changes return shapes used by the domain (stdClass objects flow through `RequestCheckService` and `PartyModeration::match`), slice 1 may grow. Split it if so.
- **Echo v2.** Its Reverb options and `window.pusherConfig` handling may change; E4 rewrites that config shortly after, so keep slice 6's change minimal.
