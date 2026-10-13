# Tabler is upgraded from 1.0.0-beta20 to 1.6, which drops Bootstrap JS

The UI was on `@tabler/core 1.0.0-beta20`, a pre-release, imported as SCSS source. The token theme system (E7 R5) and the Inertia shell need a stable base that exposes `--tblr-*` CSS variables.

We upgrade `@tabler/core` to 1.6.x (with `@tabler/icons-webfont`) and keep importing it as SCSS source. Tabler 1.6 no longer bundles Bootstrap's JavaScript, so components that relied on Bootstrap's JS plugins (dropdowns, modals, collapse, tooltips) get their behaviour from our own Vue components or small scripts instead. Bootstrap's CSS still ships inside Tabler.

Blade pages are fixed only where the upgrade visibly regresses them, not redesigned, because E9 replaces them anyway.

The cost is that any Blade page using a Bootstrap JS data attribute no longer works silently, so those interactions must be checked by hand on each page after the upgrade. In return we are off a beta and design tokens map directly onto Tabler's variables.
