# Theme overrides are emitted for the light and dark scopes

Tabler redefines many `--tblr-*` variables on `[data-bs-theme=dark]` (link colour, body background, emphasis colours). A theme author's `:root{--tblr-link-color:#a86ef0}` has the same specificity as that rule but sits on an ancestor, so the body-level dark value wins and the override is silently ignored in dark mode. ADR 0008 solved this for our own `--mp-*` defaults only.

`Theme::renderCss()` now emits the generated token block on `:root, [data-bs-theme=light], [data-bs-theme=dark]`, and rewrites every `:root` selector in a theme's custom CSS to `:is(:root, [data-bs-theme=light], [data-bs-theme=dark])`. `:is()` keeps the specificity of its most specific argument, so compound and descendant selectors such as `:root.x .y` keep their meaning, and the theme stylesheet loads after the app CSS so it wins ties. Theme authors keep writing plain `:root{...}`.

The live preview in `resources/js/lib/theme.ts` sets the derived link variables on `<body>` as well as `<html>` for the same reason.

The rewrite is a text substitution, not a CSS parse: a literal `:root` inside a string or comment in custom CSS is rewritten too. This is acceptable for admin-authored theme CSS.
