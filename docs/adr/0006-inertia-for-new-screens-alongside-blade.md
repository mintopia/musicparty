# New screens are Inertia pages, and Blade pages stay until they are migrated

The v2 UI is Blade pages with Vue components mounted globally from `app.js`. New screens (E8) need shared layout, typed props and client-side navigation, which that model does not give us.

We adopt Inertia with Vue 3, using the first-party Laravel adapter (`inertiajs/inertia-laravel`) and `@inertiajs/vue3`. `HandleInertiaRequests` is registered on the `web` group and Inertia pages render through a root `app.blade.php` view. Existing Blade pages keep their own layout and the legacy `app.ts` entry, so they continue to work unchanged. Vite builds both entries, `app.ts` and `inertia.ts`, and they share one stylesheet.

Alternatives considered: a separate SPA with an API would duplicate auth, routing and validation that Laravel already provides, and rewriting every Blade page in one step would block E8 on E9. Inertia lets pages migrate one at a time.

The cost is two front-end entry points and two layouts until E9 finishes the migration, and Inertia page props become the contract between controllers and Vue, so they must be kept typed (see ADR 0005).
