# OPcache with timestamp checks off; JIT off

The production image uses `php.ini-production`, so OPcache is on with default sizes and `opcache.validate_timestamps=1`, and the JIT is off. The 2026-10-10 v3 review asked for best practice and noted the debate about PHP's JIT.

The app runs under Octane on FrankenPHP: the framework boots once per worker and stays in memory, so per-request compile and bootstrap cost is already gone. The remaining work is I/O-bound (MariaDB, Redis, Spotify, Reverb). The JIT speeds up CPU-bound PHP and measurably helps little in Laravel request handling, while it has a history of version-specific crashes and miscompilation, and it costs memory per worker.

Decision:
- OPcache is on for web and CLI (Horizon, the scheduler and Reverb are long-running CLI processes): `opcache.enable_cli=1`, `opcache.validate_timestamps=0` (the image is immutable), `opcache.memory_consumption=256`, `opcache.interned_strings_buffer=32`, `opcache.max_accelerated_files=20000`.
- The JIT is disabled (`opcache.jit=disable`, `opcache.jit_buffer_size=0`).
- No preloading. Octane workers already hold the framework in memory.
- The development image keeps `validate_timestamps=1` so code edits apply.

Consequences: predictable memory and behaviour. If profiling later shows a CPU-bound hot path, the JIT can be trialled with `opcache.jit=tracing` behind a load test, and this ADR updated.
