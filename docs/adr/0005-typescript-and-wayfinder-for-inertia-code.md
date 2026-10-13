# New front-end code is TypeScript and routes come from Wayfinder

The UI moves to Inertia pages rendered by Vue 3. Blade pages and their `app.js` entry stay in place; Vite builds a second entry, `inertia.ts`, alongside it and both share one stylesheet.

All new Inertia code is TypeScript, type-checked by `vue-tsc`. We pin TypeScript to 6.x because `vue-tsc` needs the JavaScript compiler API, which the native TypeScript 7 does not expose. Revisit when `vue-tsc` supports 7.

Routes in Inertia code are imported from `laravel/wayfinder`, which generates typed functions from the Laravel route list during the Vite build (`@laravel/vite-plugin-wayfinder`). Hard-coded URL strings are not allowed in new components. The generated files are git-ignored and rebuilt on every build.

Wayfinder is the first-party Laravel package for this, so no alternative such as Ziggy was considered. The cost is that a route must exist, and be named, before TypeScript can reference it.
