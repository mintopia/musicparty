# Design direction, round 2: party mockups (#415, epic #414)

Round 1 invented a new look and was rejected. Round 2 starts from the app as it is built today and the designs
the user already approved (Dashboard control room v5, guest player band, TV, search results), and refines them.
Mode is Operate: scanability and consistency first; the party feel lives in the player band, the TV page, album
art and precise details, not in a restyle. Use GLOSSARY.md words: Request, Queue, Fallback Playlist, Player,
Web Player, Dashboard, Flagged Request, Scheduled Request, Played Track.

## Rules

1. **Start from the live app.** For every page, begin with the real rendered DOM (grab it from the dev app; see
   "Getting real markup") or, for pages that are not reachable, the Vue component template. Keep Tabler, the
   AppLayout shell, and the app's components. Change only what the brief lists, and say what changed in the
   section's notes.
2. **Reuse unchanged** where the brief says so (TV page, guest player band, search results): render them
   faithfully from the current build. These sections carry `<span class="mock-tag is-unchanged">Unchanged</span>`.
3. **Refinements are local.** New CSS goes in `assets/mockup.css` (shared) or a page-local `<style>` in the
   template (one page only). Never edit `app.css` or `components.css`; they are copies of the build.
4. **Light and dark.** Every page group has at least one dark frame. Dark is the app's own dark mode, not a new
   palette: `data-theme="dark"` on a frame sets `data-bs-theme="dark"` on `<body>`, exactly as
   `resources/views/app.blade.php` does when the active theme has Dark Mode on (`themes.dark_mode`). Use Tabler/`--mp-*` tokens
   (`--tblr-body-color`, `--tblr-secondary-color`, `--tblr-bg-surface`, `--tblr-border-color`, `--mp-text-*`)
   so both themes work; never hard-code `#fff` or greys on page surfaces. The sidebar and the deck/player
   panels are dark in both themes already.

## Stylesheets in every frame
`app.css` (compiled Tabler 1.6 + app sass: tokens, dark scope, `.setting-row`, `.deck-panel` etc.) →
`components.css` (every Vue-scoped component stylesheet from the same build: PartyPlayer, Tv, Dashboard,
TrackRow, VoteControl, Search, ThemeForm, Mods, Edit, useProgress, useRating …) → `mockup.css`.
Scoped styles match on `data-v-*` attributes, so grabbed markup must keep them.

## Getting real markup
`node $SCRATCH/grab-dom.cjs <path> <role> <desktop|phone|tv> <out.html> [selector] [waitMs]` (helper in the
session scratchpad; sessions in `sessions.txt` next to it; dev party code `NPRN`). It removes scripts and the
debugbar, keeps `data-v-*`, and swaps external cover URLs for `assets/art/cover-1..8.svg` stand-ins. Paste the
result into a frame's `<template>`, then edit content and apply the brief's changes. If the page cannot be
reached (new page, missing state), build from the component template in `resources/js` using the same classes.

## Type (#425)
Applied inside `.page-body` only, through `:where()`, so component sizes (TV, player band, deck panels, search
tiles) never change. h1 32/1.15/700, h2 24/1.25/650, h3 19/1.35/600 (`.card-title` too), h4 16/1.4/600, body 15/1.55,
small 13. One h1 per page; never skip a level (page h1 → card titles h2/h3 in order). Sentence case.

## Shell
Real AppLayout: dark vertical sidebar (party group: Dashboard, Queue, Search, Requests, Moderation + flagged
badge, Members, Settings for managers; Queue, Search for guests), header with Tabler `.breadcrumb` (separators come
from Tabler) and avatar, `.page-body > .container-xl`. First element: `<a class="mp-skip" href="#main">Skip to
content</a>`, and `<main id="main" class="page-body">`. The skip link is `position: fixed; z-index: 1090`, above
the sidebar (1030); round 1 had it at `absolute`/1000 under the sidebar. Width rule: `.mp-narrow` (960px, centred)
when sparse (Queue, Search, forms, Settings); full `.container-xl` when dense (Dashboard, tables, Admin dashboard).
Tabbed groups (Settings, Moderation) keep one width across tabs. `.mp-page-head` = h1 + one-line summary left,
primary action right.

## What exists in mockup.css
- `.mp-skip`, `.mp-page-head`, `.mp-narrow`, `.mp-stack(-sm)`, `.mp-num` (tabular numerals).
- Toast action: the app's Toast markup (`.toast-container > .toast > .d-flex > .toast-body + .btn-close`) plus
  `button.mp-toast-action` ("Undo", "Retry"). `.toast-container.mp-static` shows it inline in a frame.
- Overflow menus: Tabler `.dropdown-menu.show` with icon + visible label (+ optional `<small>` reason);
  items are 44px on touch.
- `.mp-searchbar > .mp-search (i.ti + input.form-control + .mp-search-count) + .mp-chips > button.mp-chip
  [aria-pressed]` for table pages: one field that also understands filters typed as words (`role:vip`,
  `flagged`, `sent:no`), with the same filters as chips.
- `ol.mp-steps > li(.is-done|[aria-current=step])` for Create Party.
- `input.mp-code-input(.is-invalid)` for the four-letter party code.
- `ul.mp-checks > li` (status dot, `.mp-check-label` + `.mp-check-detail`, action) for Admin dashboard and
  Diagnostics-style lists. Use Tabler `.status-dot.status-green|yellow|red|secondary`.
- `.mp-swatch(.is-unset)` with `--swatch` for theme colour fields: unset shows the inherited default, dashed,
  labelled "Default", never black.
- Use Tabler for everything else: `.card`, `.table`, `.badge`, `.btn`, `.form-*`, `.nav-tabs`, `.empty`,
  `.status-dot`, `.avatar`, `.list-group`.

## Copy
Controls name their action ("Open Web Player", "Start on Spotify", "Undo", "Retry"). Ages in words
("45 days ago", "2 minutes ago"), never raw minutes. Errors say what happened and what to do next. Party codes are
four letters, no digits (`KXQB`); the join URL is `/parties/KXQB`. Tabler icons only; no emoji or unicode glyphs.

## Mock content
Party "Coyote LAN 26", code `KXQB`, host Jess (owner), moderator Dev, guests Ravi, Mina, Priya, Sam.
Tracks: Mr. Brightside (The Killers), Sandstorm (Darude), Dancing Queen (ABBA), Levels (Avicii), Everybody
(Backstreet Boys), Midnight City (M83), Get Lucky (Daft Punk). Album art: `assets/art/cover-N.svg`.
Fallback Playlist: "Coyote LAN classics". Use the same names on every page.

## Frame conventions
Copy `_template.html`. Each screen is `<figure class="mock-frame" data-frame="desktop|phone|tv" id="unique-id">`
with `<figcaption><b>Name</b> size</figcaption>` and the screen markup inside `<template>`. `assets/mockup.js`
renders it in an iframe at 1440 / 390 / 1920×1080 so media queries work. Attributes: `data-theme="dark"`,
`data-height` (min height), `data-fixed` (clip to that height), `data-css` (extra files from assets/),
`data-body-class`. Group frames in `.mock-section` (h2 + status tag + p) and `.mock-row`; notes in
`ul.mock-notes` say what changed and why (finding or user feedback). Status tags: `.mock-tag.is-unchanged`,
`.is-changed`, `.is-new`. Show menus, toasts and dialogs in their open state.
Capture: `node screenshot.mjs [page.html]` → `screenshots/<page>-<frame-id>.png` at 1:1. Look at every frame.
