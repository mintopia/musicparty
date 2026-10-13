# Tabler is upgraded from 1.0.0-beta20 to 1.6

The UI was on `@tabler/core 1.0.0-beta20`, a pre-release. The token theme system (E7 R5) and the Inertia shell need a stable base that exposes `--tblr-*` CSS variables.

We upgrade `@tabler/core` to 1.6.x, with `@tabler/icons-webfont`. The legacy `app.ts` entry imports Tabler's compiled assets (`~tabler/dist/js/tabler.min.js` and `~tabler/dist/css/tabler.css`) and layers `resources/sass/app.scss` on top.

Tabler 1.6 dropped the Bootstrap JavaScript it used to depend on. The compiled `tabler.min.js` still provides the behaviour that the Blade layouts use through `data-bs-toggle`, `data-bs-target` and `data-bs-theme` (sidebar collapse, dropdowns, dark mode), so the Blade pages keep working without new code. Inertia pages do not use those attributes and build their own interactions in Vue.

Blade pages are fixed only where the upgrade visibly regresses them, not redesigned, because E9 replaces them anyway.

The cost is that Blade pages depend on the data-attribute behaviour of Tabler's bundled script, so a future Tabler upgrade must re-check them by hand. In return we are off a beta and design tokens map directly onto Tabler's variables.
