# Design direction: party mockups (#415, epic #414)

Carry the TV page and now-playing strip into the rest of the site. Do not restyle Tabler: keep its shell, forms and
tables, and add a dark **stage** band plus a small set of party primitives. Mode is Operate: precision over decoration.
Use GLOSSARY.md words: Request, Queue, Fallback Playlist, Player, Web Player, Dashboard, Flagged Request.

## Tokens (assets/mockup.css `:root`, on top of `resources/sass/_tokens.scss`)
- Stage: `--mp-ink #0f1014` base, `--mp-stage-tint` (set per screen from album art; any oklch hue), white text,
  `--mp-on-muted` 74% white. Signals: `--mp-live` green, `--mp-warn #ffd866`, `--mp-alert #fca5a5`.
- Light content keeps Tabler tokens: `--tblr-primary` for actions, `--mp-text-*` for coloured text.
- Spacing `--mp-space-1..8`: 4 8 12 16 24 32 48 64. Radius: card 12, control 8, art 6, pills 999.
- Album art stand-in: `.mp-art` with `--hue` (0–360) and a `ti-vinyl` icon; sizes `-sm` 32, default 48, `-md` 96, `-lg` 168.

## Type scale (replaces flat 14/16/18/20, ratio ~1.25)
| Role | Size / line | Weight | Tracking |
|---|---|---|---|
| Display (`.mp-display`, status headline) | clamp(28px…40px) / 1.1 | 700 | -0.025em |
| h1 / `.page-title` | 32px / 1.15 | 700 | -0.02em |
| h2 | 24px / 1.25 | 650 | -0.015em |
| h3 | 19px / 1.35 | 600 | -0.01em |
| h4 | 16px / 1.4 | 600 | 0 |
| Body | 15px / 1.55 | 400 | 0 |
| Small (`.mp-small`, meta, crumbs) | 13px / 1.45 | 400–600 | 0 |
| Table cells | 14px | | |
One h1 per page; never skip levels (h1 → h2 → h3). Card titles are h2 (sized as h3 via `.mp-card-head`).
TV keeps its own `--u` scale (title 88px, artist 40px, code 52px at 1080p).

## Page shell
Tabler vertical dark sidebar + top header. Header holds `.mp-crumbs` (chevron separators, last item
`aria-current="page"`) and the avatar. First element in body: `<a class="mp-skip" href="#main">Skip to content</a>`.
Content goes in `<main id="main" class="page-body">` → `.container-xl`. Width rule: centred `.mp-narrow` (960px)
when sparse (Queue, forms, Web Player); full `.container-xl` when dense (tables, Dashboard).
Party pages put a `.mp-stage` band between header and main (now playing, or the Dashboard status). Under 992px the
sidebar collapses to a top bar with toggler, logo, avatar (see `_template.html` phone frame).
`.mp-page-head` = h1 + one-line summary on the left, primary action on the right.

## Primitives (assets/mockup.css)
- `.mp-stage > .mp-stage-inner` dark band; set `style="--mp-stage-tint: oklch(58% .17 25)"`. Use `.mp-btn-light`
  (primary), `.mp-btn-ghost`, `.mp-btn-spotify` inside it; Tabler `.btn` elsewhere.
- `.mp-status[data-state=playing|paused|silent|offline]` → `.mp-status-art`, `h2.mp-status-headline` (signal dot is
  automatic), `p.mp-status-detail`, `.mp-status-actions`. Headline answers "is music coming out?"; actions are next steps.
- `.mp-pill` + `.is-live|.is-warn|.is-alert` status pills (adapt on/off stage).
- `.mp-card` > `.mp-card-head` (h2 + actions) + `.mp-card-body`; `.mp-card-flush` for lists. No nested cards.
- `ol.mp-tracks > li.mp-track` grid: `.mp-track-pos`, `.mp-art`, `.mp-track-text` (`p.mp-track-title > span` +
  optional `.mp-you`; `p.mp-track-artist` / `p.mp-track-by`), `.mp-track-meta`, `.mp-track-actions`.
  `.is-mine` tints a guest's own Request. Below 768px it stacks: art | title/artist + meta | actions.
- `.mp-credit` with `ti-confetti`: "From the party's Fallback Playlist" (never "Fallback Track").
- `.mp-vote` > `button.mp-vote-btn.is-up|.is-down|.is-like[aria-pressed]` + `.mp-vote-score`. 40px, 44px on touch/phone.
- `.mp-iconbtn` (+ `.is-danger`) for single icon actions; always `aria-label`. Put `.mp-sep` between votes and remove.
- `.mp-overflow(.is-open)` > `.mp-iconbtn` + `ul.mp-overflow-menu > li > button.mp-overflow-item(.is-danger)` with
  icon + visible label (+ optional `<small>` reason).
- `.mp-toasts(.is-static)` > `.mp-toast(.is-error)`: icon, `.mp-toast-text` (name the track in `<b>`),
  `.mp-toast-action` ("Undo", "Retry"), dismiss `.mp-iconbtn`. Fixed bottom-centre; `.is-static` for inline display.
- `details.mp-disclosure > summary (+ <small>) + .mp-disclosure-body` for Diagnostics and other fold-aways;
  `dl.mp-facts` for label/value pairs inside.
- `.mp-searchbar` > `.mp-search` (icon + 48px input) + `.mp-chips > button.mp-chip[aria-pressed]` (with `.mp-num` count).
- `ol.mp-steps > li(.is-done|[aria-current=step])` three-step header.
- `.mp-code` (party code display), `input.mp-code-input` (code entry), `.mp-empty` (icon, h2, p, `.mp-empty-actions`)
  for empty, error, 404 and load-retry states. `.mp-kbd` for shortcut hints. `.mp-stack`, `.mp-stack-sm` for gaps.

## Copy
Controls name their action ("Open Web Player", "Start on Spotify", "Undo", "Retry"). Ages in words ("45 days ago").
Errors say what happened and what to do next. Sentence case everywhere. No emoji, no unicode glyph icons: Tabler icons.

## Frame conventions
Copy `_template.html`. Each screen is `<figure class="mock-frame" data-frame="desktop|phone|tv" id="unique-id">`
with `<figcaption><b>Name</b> size</figcaption>` and the screen markup inside `<template>`. `assets/mockup.js` renders
it in an iframe at 1440 / 390 / 1920×1080 so media queries work. Optional attributes: `data-theme="dark"`,
`data-height` (min height), `data-fixed` (clip to that height), `data-css="tv.css"` (extra files from assets/).
Group frames in `.mock-section` (h2 + p) and `.mock-row`; add design notes in `ul.mock-notes`.
Images: `assets/…` paths (frames resolve relative to this folder). Show menus/toasts in their open state.
Capture: `node screenshot.mjs [page.html]` → `screenshots/<page>-<frame-id>.png` at 1:1. Check every frame there.
Compiled CSS in assets/ is copied from the app build (Tabler + app = `app.css`; component CSS files are Vue-scoped,
reference only). Do not edit them; put proposals in `mockup.css` or a page-local `<style>` inside the template.
