# Music Party

A collaborative music playlist intended for LAN parties and parties. Users
login with their social credentials (Discord, other OAuth2 providers) and
can view the queue, search for tracks, upvote and downvote.

## House rules

 - Use subagents for tasks.
 - Use multiple subagents in parallel working as a team, with agent messaging
   to co-ordinate.
 - A subagent whose result runs long writes it to a file under the session
   scratchpad and sends only a one-line summary plus that path. Never paste full
   reports, reviews, or findings into an agent message — they flood the lead's
   context.
 - Subagents must use an appropriate model, defaults:
   - Explore, Coding, Code Reviews: Sonnet / Terra
   - Codebase Mapping: jcodemunch MCP and Sonnet / Terra
   - Reasoning and Planning: Opus / Sol
   - Trivial, Documentation: Haiku / Luna
 - Explicitly specify the model when starting a subagent.
 - Subagents must use an appropriate subagent type.
 - Do not start a subagent without explicitly setting the model.
 - When building UI to match a mockup, it MUST match the mockup
 - When work is complete, before merging or finishing, run /no-comments or $no-comments

### Local Deployment for Development

There is a local deployment for development/user verification using .env.

 - Application URL: https://musicparty.sailing-coyote.ws.cloudagent.mintopia.net

Websockets are served from the same hostname (see ADR 0004); there is no separate Reverb URL.

It is linked with Discord for auth and Spotify for music.
It is running in docker with Reverb, Horizon, MariaDB, Redis.

### Testing

While editing, run specific unit tests only. CI will be configured to do full
testing, linting, static analysis, etc. Any failures in CI will require fixes.

Flaky tests are not allowed. If any tests are considered flaky, then they must
be fixed.

Mock APIs and interfaces must be used where possible. Tests should be run in parallel
where possible as long as it does not cause CPU load to spike too high.

Run the full suite only when work is completed. If tests fail, fix the issue
and re-run just those tests.

### Branching and rebasing

Work only on the branch you start on. The integration branch is `feature/v3-rewrite`
and the `epic/*` branches; `main` is the stale release branch (`origin/main`,
`origin/HEAD`, and `v1.0.0` all sit on the tagged release, far behind develop).

Never `git rebase origin/main` (or `origin`/`origin/HEAD`) — that replays your
work onto the release tip and drags in a mountain of divergence. If you must
rebase, rebase onto your task's own base branch (the `feature/v3-rewrite` or
`epic/*` ref you started from), never the release line.

Never work directly in develop or feature/v3-rewrite. If you would work directly
in those, create a worktree and then merge when done.

Cleanup branches and worktrees when they are finished and merged or abandoned.

## Code and Technology Guidelines

 - PHP 8
   - FrankenPHP
   - PEST
   - RectorPHP
   - PHPStan (Level 8)
   - xdebug for code coverage
 - MariaDB
 - Laravel
   - Boost MCP
   - Octane
   - Horizon
   - Socialite
   - Inertia
   - Reverb
   - Pint
 - Redis
 - Docker
 - Docker Compose
 - VueJS
   - Vite

Where there is a first-party Laravel package to achieve something, it should be
used or there should be an explicit ADR why not.

## Code exploration policy

Always use jCodeMunch-MCP for code navigation. Never fall back to Read, Grep, Glob, or Bash for code exploration.
**Exception:** use `Read` when you are about to edit a file — the harness requires a `Read` before `Edit`/`Write`. Use jCodeMunch to *find and understand* code, then `Read` only the file you are changing.

This server runs the **front door** surface: three tools reach every jCodeMunch capability, so the tool list stays small and the catalogue is fetched only when you need it.

**Start any session:**
1. `order { "action": "resolve_repo", "args": { "path": "." } }` — confirm the project is indexed. If it is not: `order { "action": "index_folder", "args": { "path": "." } }`

**Then, for any task:**
- Know what you want → `order { "action": "<name>", "args": { ... } }`
- Know the goal, not the tool → `route { "query": "your task in a sentence" }` picks the action and shapes the arguments
- Want to see what exists → `menu { "query": "what you are trying to do" }` returns matching actions with example arguments
- Want the whole catalogue and the usage rules → `jcodemunch_guide`

`menu` and `jcodemunch_guide` list every action this server can run, including ones absent from your tool list. That is expected: the front door is the way to call them.

**Interpreting results:**
- A `verdict` of `no_implementation_found` is evidence of absence. Report the gap; do not re-search with different wording.
- A `verdict` of `degraded` means a channel was unavailable, so absence is NOT proven. Read the note before relying on the result.
- `source: ""` alongside `source_status` means the body could not be read, not that the symbol is empty.

**After editing files:**
- With PostToolUse hooks installed (Claude Code), edited files are reindexed automatically.
- Otherwise `order { "action": "register_edit", "args": { "paths": [...] } }` after an edit, batched for bulk changes.

**Announce your model once per session** so the server can size its answers: `announce_model { "model": "<your-model-id>" }`.

## Agent skills

### Issue tracker

GitHub Issues via `gh`; every ticket set hangs off an `epic` parent with native sub-issues and `blocked_by` edges. See `docs/agents/issue-tracker.md`.

### Triage labels

Default five triage labels plus `epic` and work-type labels (`coding`, `research`, `testing`, `documentation`). See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: `GLOSSARY.md` and `docs/adr/` at the repo root. See `docs/agents/domain.md`.

===

<laravel-boost-guidelines>
=== .ai/installed-versions rules ===

## Installed Versions

Versions below are taken from `composer.lock` and `package-lock.json`. Use the APIs for these versions.

- php - ^8.5 (`composer.json`)
- laravel/framework - v13.35.0
- laravel/horizon - v5.50.0
- laravel/octane - v2.21.0
- laravel/prompts - v0.3.25
- laravel/pulse - v1.8.1
- laravel/reverb - v1.12.0
- laravel/sanctum - v4.3.3
- laravel/socialite - v5.31.0
- laravel/tinker - v3.0.2
- laravel/boost - v2.10.3 (dev)
- laravel/pint - v1.32.1 (dev)
- pestphp/pest - v5.3.2 (dev)
- phpunit/phpunit - 13.4.2 (dev)
- larastan/larastan - v3.13.0 (dev)
- rector/rector - 2.7.0 (dev)
- vue - 3.5.43
- vite - 8.3.4
- laravel-vite-plugin - 3.2.0
- @vitejs/plugin-vue - 6.0.10
- laravel-echo - 2.6.1
- pusher-js - 8.6.0
- axios - 1.20.0
- sass - 1.105.1
- @tabler/core - 1.0.0-beta20

=== .ai/laravel-structure rules ===

## Laravel 13 Streamlined Structure

This application targets the streamlined Laravel 11+ skeleton, not the Laravel 10 layout.

- Application configuration lives in `bootstrap/app.php` via `Application::configure()`: `withRouting`, `withMiddleware`, `withExceptions`.
- Register middleware (global, groups, aliases) in `withMiddleware`; there is no `app/Http/Kernel.php`.
- Handle exceptions in `withExceptions`; there is no `app/Exceptions/Handler.php`.
- Define the schedule in `routes/console.php` (or `withSchedule`); there is no `app/Console/Kernel.php`.
- Register service providers in `bootstrap/providers.php`, not `config/app.php`.
- Use current env names such as `BROADCAST_CONNECTION` and `CACHE_STORE`.
- Rate limits are defined in a service provider's `boot()` method.
- Until the E3 skeleton migration lands, the legacy `Kernel`/`Handler` classes may still exist; do not add new registrations to them when working on the migration.

=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== laravel-octane/core rules ===

# Laravel Octane

This application uses Laravel Octane, a long-running PHP server. The application bootstraps once and handles many requests within the same process.

- Never store request-specific state in singletons or static properties, because it can leak across requests.
- Use `config('octane.server')` to detect the active driver (`swoole`, `roadrunner`, or `frankenphp`).
- Prefer scoped bindings (`$this->app->scoped()`) over singletons for per-request services.

When working on Octane-specific features (concurrency, shared tables, memory, driver configuration, testing), invoke `octane-development` for detailed rules.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

</laravel-boost-guidelines>
