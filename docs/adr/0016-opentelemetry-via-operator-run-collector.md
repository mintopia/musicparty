# OpenTelemetry is exported over OTLP to a collector the operator runs

The app ships the `open-telemetry/*` packages with Laravel auto-instrumentation, `OTEL_*` settings in `production.env`, and a `collector.yml` in the repository root. That collector config holds a working Basic credential for an OpenObserve ingest endpoint in plaintext, and scrapes Caddy's admin API and the Prometheus endpoint with paths and access that do not match the app. None of it was recorded in an ADR. Pulse and Nightwatch are first-party alternatives, but neither exports traces to a backend the operator chooses.

Decision:
- OpenTelemetry stays supported and off by default. The app exports traces, metrics and logs over OTLP to an endpoint the operator configures with the standard `OTEL_EXPORTER_OTLP_ENDPOINT`, `OTEL_EXPORTER_OTLP_PROTOCOL` and `OTEL_EXPORTER_OTLP_HEADERS` variables. That endpoint is a collector the operator runs (a sidecar or a separate process). The app holds no backend credentials.
- `collector.yml` is deleted from the repository and from git tracking. The README describes, without shipping a file, what an operator's collector needs: an OTLP receiver, and optionally a Prometheus receiver that scrapes the app with `PROMETHEUS_TOKEN`.
- Pulse and Horizon remain the built-in dashboards (ADR-0020). Prometheus remains the metrics endpoint (ADR-0009).

Consequences: no secrets in the repository. Operators who do not run a collector leave OpenTelemetry off and lose nothing else. The leaked credential must be rotated by its owner and, if the repository is or becomes public, purged from history. Both are operator steps.
