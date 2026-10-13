# Mods are discovered by scanning app/Mods once per process, not from a cached manifest

A core `ModServiceProvider` (listed in `bootstrap/providers.php`) globs `app/Mods/*` when it registers. A directory `<Name>` with a `<Name>Mod` manifest implementing `App\Mods\Contracts\Mod` is a mod, and its `<Name>ServiceProvider`, if present, is registered with the application. No mod is named in `bootstrap/providers.php` or `config/app.php`, so adding a directory is the only step needed.

Under Octane the application boots once per worker, so the scan costs one `glob` and a handful of class loads per worker start, and nothing per request. `ModRegistry` is bound as an instance holding only immutable manifests, so it carries no request state between requests. A manifest cache built by `mods:sync` or `optimize` was rejected: it adds a second source of truth that can go stale against the directory, to save a cost that is already paid once per worker.

The `mods` and `mod_settings` tables are kept in line with the manifests by `php artisan mods:sync`, run by the container entrypoint before the Octane server starts. A mod whose directory has gone is marked `removed_at`, removed from every Party, and its settings are kept. Per-Party enablement lives in `party_mods`; enabling or disabling calls the manifest's `onEnable` / `onDisable` hook.
