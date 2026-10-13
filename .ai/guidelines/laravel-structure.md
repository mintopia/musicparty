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
