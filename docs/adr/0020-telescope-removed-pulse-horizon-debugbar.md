# Telescope is removed; Pulse and Horizon in production, Debugbar in development

The v3 audit fixes removed Laravel Telescope: the load test found its ingest stalled Reverb, and it writes every request and query to the database. It was left as an open question in that change's design and never recorded. `barryvdh/laravel-debugbar` stayed as a dev dependency.

Decision:
- Telescope is not installed. `/telescope` returns 404.
- Pulse and Horizon are the operational dashboards, behind the admin gate.
- Laravel Debugbar stays as a development-only dependency. It is never installed in the production image (`composer install --no-dev`) and is disabled unless `APP_DEBUG` is true and the environment is `local`.
- OpenTelemetry covers request tracing for operators who want it (ADR-0016).

Consequences: no request-capture tool in production. Developers use Debugbar locally; operators use Pulse, Horizon, Prometheus and, optionally, OpenTelemetry.
