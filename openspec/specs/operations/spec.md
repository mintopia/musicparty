# operations Specification

## Purpose
Synced from the v3-review-fixes change.

## Requirements

### Requirement: Isolated test suite
The automated test suite SHALL run against an in-memory SQLite database and SHALL need no other service (no MariaDB, Redis, Reverb or Music Provider), locally and in CI. It SHALL refuse to run against any other database connection.

#### Scenario: Developer runs tests
- **WHEN** a developer runs the suite in a checkout whose environment file points at their development database
- **THEN** the suite runs on in-memory SQLite and the development database is untouched

#### Scenario: Misconfigured connection
- **WHEN** the suite is started with a database connection other than in-memory SQLite
- **THEN** it stops before running any test, naming the connection

#### Scenario: CI
- **WHEN** CI runs the suite
- **THEN** no database or Redis service container is started for it

### Requirement: Database install and upgrade
Migrations SHALL install the full v3 schema on an empty database, upgrade a v2 database to v3 (data in tables that v3 replaces may be lost), and leave an existing v3 database's data intact. Migrations SHALL run on MariaDB and on SQLite. Running them concurrently from two containers SHALL apply them once.

#### Scenario: Fresh install
- **WHEN** migrations run on an empty MariaDB database
- **THEN** the v3 schema is created

#### Scenario: Upgrade from v2
- **WHEN** migrations run on a database at the final v2 schema
- **THEN** the result is the v3 schema, with no v2-only tables left

#### Scenario: Existing v3 database
- **WHEN** migrations run on a database that already has the v3 schema and data
- **THEN** the data is kept and only newer migrations are applied

#### Scenario: Two deploys at once
- **WHEN** two containers run the migration step at the same time
- **THEN** one applies the migrations and the other waits and then exits successfully

### Requirement: Unprivileged containers
Every container built from the published image SHALL drop to an unprivileged user before running its role. The user and group ids SHALL be configurable at runtime, and the container SHALL make only the directories the application writes to owned by that user. Every network listener in the image SHALL use an unprivileged port. The web server's administration interface MUST NOT be reachable from outside its own container.

#### Scenario: Default user
- **WHEN** the image starts with no user settings
- **THEN** every application process runs as uid 1000 and gid 1000

#### Scenario: Custom user
- **WHEN** an Operator sets the user id and group id settings to match their bind-mounted directories
- **THEN** application processes run with those ids and the files they write on the host are owned by them

#### Scenario: Admin interface
- **WHEN** another container on the same network connects to the web container's administration port
- **THEN** the connection is refused

### Requirement: One entrypoint for every role
The image SHALL have one entrypoint that runs any role (web, Horizon, scheduler, realtime server, migration, or an arbitrary artisan command) given as the container command, and that caches configuration before starting it. Shipped Compose files SHALL NOT override the entrypoint.

#### Scenario: Worker roles boot cached
- **WHEN** the Horizon, scheduler and realtime server containers start
- **THEN** each runs with cached configuration

#### Scenario: Artisan
- **WHEN** an Operator runs a one-off artisan command through the shipped Compose file
- **THEN** it runs as the unprivileged user with the same environment

### Requirement: Ordered and healthy start-up
The shipped Compose files SHALL give the database, Redis and the web role healthchecks. Application roles SHALL start only after the database and Redis are healthy and, in the production example, after the migration role has completed successfully.

#### Scenario: First start
- **WHEN** an Operator starts the production example on empty data directories
- **THEN** the database becomes healthy, migrations run once, and then the web, Horizon, scheduler and realtime containers start without connection errors

#### Scenario: Failed migration
- **WHEN** the migration role fails
- **THEN** the web, Horizon and scheduler containers do not start

### Requirement: Graceful shutdown
Stopping or redeploying SHALL give Horizon, the web role and the realtime server long enough to finish in-flight work before they are killed.

#### Scenario: Redeploy during a job
- **WHEN** an Operator redeploys while Horizon is running a 50-second job
- **THEN** the job finishes before the Horizon container is stopped

### Requirement: Persistent data on bind mounts
The shipped Compose files SHALL store all persistent data on bind mounts, never named volumes: the database, Redis data with append-only persistence, uploaded files and logs. Redis data SHALL survive a container recreate.

#### Scenario: Redis recreated mid-party
- **WHEN** the Redis container is recreated during a Party
- **THEN** logged-in sessions remain valid and queued jobs are still processed

### Requirement: Shared cache and sessions by default
The cache, session and queue stores SHALL default to Redis, configured through Laravel's standard environment settings, so that the web, Horizon and realtime processes share locks, buffers and rate-limit state. Other stores SHALL be used only by the test suite.

#### Scenario: Minimal environment
- **WHEN** the application starts with no cache, session or queue setting
- **THEN** all three use Redis

### Requirement: Trusted proxies
The set of proxies whose forwarding headers are trusted SHALL be configurable, defaulting to all proxies. The Operator documentation SHALL state that with the default, any address-based access check is only as strong as the guarantee that clients cannot reach the application except through the proxy.

#### Scenario: Restricted proxies
- **WHEN** an Operator sets trusted proxies to their reverse proxy's network range
- **THEN** forwarding headers from any other address are ignored when determining the client address

#### Scenario: Default
- **WHEN** no trusted proxies are configured
- **THEN** forwarding headers from any address are trusted

### Requirement: Telemetry export
The application SHALL support exporting OpenTelemetry data over OTLP to an endpoint the Operator configures, and SHALL be off by default. The repository MUST NOT contain telemetry backend credentials or a collector configuration.

#### Scenario: Collector configured
- **WHEN** an Operator enables OpenTelemetry and sets the OTLP endpoint to their collector
- **THEN** traces for web requests and queued jobs are sent to that endpoint

#### Scenario: Not configured
- **WHEN** OpenTelemetry is not enabled
- **THEN** the application runs normally and sends nothing

### Requirement: Production web hardening
The web server SHALL send security headers (content type sniffing off, frame embedding restricted, referrer policy, and HSTS when served over HTTPS), SHALL limit request body size, and SHALL redact credentials passed in the query string from its access logs. PHP SHALL run with OPcache enabled, timestamp validation off in production, and the JIT disabled (ADR-0022).

#### Scenario: Security headers
- **WHEN** a browser loads any page
- **THEN** the response carries the configured security headers

#### Scenario: Oversized upload
- **WHEN** a request body exceeds the configured limit
- **THEN** the web server refuses it with 413

#### Scenario: Token in query string
- **WHEN** a request URL carries an authorization query parameter
- **THEN** the access log shows it redacted

### Requirement: Published images
Published images SHALL be built for amd64 and arm64, each on a native runner, and combined into one multi-architecture manifest. Images SHALL be published only from commits whose CI passed, with an immutable tag per commit in addition to branch tags. Base images in Dockerfiles, Compose files and CI SHALL track the latest stable release of each component, the same version everywhere.

#### Scenario: Red commit
- **WHEN** CI fails on a commit
- **THEN** no image is published for it

#### Scenario: Multi-architecture pull
- **WHEN** an Operator on an arm64 host pulls the image
- **THEN** they receive a natively built arm64 image

### Requirement: Operator documentation
The README SHALL document how to deploy, operate and upgrade the published image, including reverse-proxy requirements with a Caddy example, trusted proxies, telemetry, resource limits and database backup. The repository SHALL NOT ship a configuration for any other reverse proxy.

#### Scenario: Topics covered
- **WHEN** an Operator reads the deployment section
- **THEN** it covers required settings, user and group ids, bind-mount directories, the reverse proxy and realtime paths, trusted proxies and their limits, an OpenTelemetry collector, Prometheus scraping, resource limits, and database backup and restore

#### Scenario: New Operator
- **WHEN** an Operator follows the README on a clean host with a domain name
- **THEN** they reach a working instance with realtime updates, without editing any shipped file

#### Scenario: Upgrade from v2
- **WHEN** an Operator running v2 follows the upgrade section
- **THEN** they back up, deploy v3, and the database is migrated automatically
