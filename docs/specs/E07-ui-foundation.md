# E7 UI foundation

Goal: deliver the Inertia + Vue 3 shell, the latest stable Tabler and a design-token theme system, so that E8 builds its new screens in Inertia from day one, alongside the existing Blade pages.

- Status: Draft
- Wave: 6
- blocked_by: E6 Architecture
- Downstream: E8 Mods, Players & Browsing (new screens in Inertia), then E9 UI migration. Shared UI building blocks for E8 are delivered here.

## Problem

- The UI is Blade plus six Vue 3 SFCs mounted into Blade with a global `createApp().mount('#app')` (`resources/js/app.js`). It is not Inertia, and no Inertia package is installed (facts-stack §1).
- Tabler is `@tabler/core 1.0.0-beta20`, imported as SCSS source (`resources/sass/app.scss:1`), with a custom `tabler-icons.scss`.
- Themes are rows in `themes` (`primary`, `nav_background`, `dark_mode`, `css`), and one theme is active instance-wide. A view composer in `AppServiceProvider` resolves it with an uncached query on every view render. `partials/_theme.blade.php` hand-maps two colours onto a handful of `--tblr-*` variables. It still references `seat_*` columns that do not exist (from another project's seating plan), and it injects `{!! $currentTheme->css !!}` raw. There are no real design tokens.
- Reverb client config is already read from `config()` (`partials/_pusher-config.blade.php`), and Echo connects to the page's own origin (E4, ADR 0004). The new UI needs only a typed `useEcho` over the same config.
- The Search page sends a Spotify-shaped legacy array (`TrackAnnotation::toSearchResult()`) to `SearchResult.vue`, which posts to hard-coded API URLs.
- The navigation (`layouts/app.blade.php:33-145`) is a dark vertical navbar with a per-Party dropdown (Queue, Search, Songs, Users, TV Mode, YouTube Player, Web Player, Moderation, Settings) and an admin dropdown. The v2 look must be kept (project memory: v3 UI must match v2).
- Known Vue 3 defects in the existing components: `beforeDestroy` instead of `beforeUnmount`, and `intervalId` never reset (`Player.vue:169-171`, `TvPlayer.vue:59-61`). These are E8/E9 concerns, but the shared composables must not repeat them.

## Goals

- Inertia (Laravel adapter plus `@inertiajs/vue3`) is installed and wired, so a controller can return an Inertia page while Blade routes keep working.
- An Inertia `AppLayout` that reproduces the v2 shell (sidebar, Party dropdown, admin menu, user menu, flash messages) on the new Tabler.
- Design tokens are CSS custom properties. A theme is a set of token values plus an optional custom stylesheet, applied identically to Blade and Inertia pages.
- A small shared component and composable kit that E8 and E9 use instead of building their own.
- The Party Search page converted end to end as the pilot, to prove the shell. E8's browsing builds on it.
- TypeScript for all new Inertia code, with the existing Vue components converted to TypeScript.

## Non-goals

- Converting pages beyond the pilot (E9 converts guest-facing pages first, admin last).
- Changing what `Player.vue`, `TvPlayer.vue`, `Upcoming.vue` and the rest do (E8/E9). Their TypeScript conversion here is type-only.
- Per-Party themes or a user Colour Scheme preference.
- Any new feature UI. Album and artist pages, the Web Player popup, mod settings and the Flagged Request queue all belong to E8.

## Requirements

1. **R1 Inertia.** Install the Inertia Laravel adapter and the Vue 3 client at the latest stable versions. Register the `HandleInertiaRequests` middleware in `bootstrap/app.php` (E3 skeleton) for the `web` group. Use a root Blade view `app.blade.php` for Inertia, and keep the existing Blade layout for Blade pages.
2. **R2 Shared props.** The middleware shares:
   - the authenticated user (id, nickname, avatar, roles, `impersonating`)
   - the user's Party memberships for the nav (code, name, `canManage`)
   - flash messages and validation errors
   - the active theme's token values and stylesheet URL
   - Reverb client config, read from `config()` and never from `env()` in views (the same origin after E4)
   - the instance name

   Lazy props are used where data is per-page.
3. **R3 Both entry points.** Vite builds both entry points: the legacy `app.js` (global mounts for Blade) and an Inertia `inertia.js`, sharing one stylesheet. Blade pages render unchanged apart from the Tabler upgrade.
4. **R4 Tabler.** Upgrade `@tabler/core` from 1.0.0-beta20 to the latest stable 1.x, along with the icons. Fix the Blade pages the upgrade breaks visually, without a redesign.
5. **R5 Design tokens.**
   - Define an `--mp-*` token set (at least primary, primary-rgb, nav-background, surface, text, muted, border, success, danger, warning, radius, font family) in one SCSS/CSS file.
   - Map the tokens onto Tabler's `--tblr-*` variables, so both Blade and Inertia pages consume them.
   - Dark mode remains a theme attribute (`data-bs-theme="dark"`).
6. **R6 Themes as tokens.**
   - Migrate `themes` to store a token map (JSON) with `dark_mode` and `css` kept. Existing themes migrate losslessly: `primary` and `nav_background` become tokens.
   - Theme CSS is served from a cached, versioned route (`/theme/{code}.css`) used by both layouts, replacing the inline `_theme` partial. The seating-plan rules are removed.
   - The admin theme form edits tokens, using colour inputs for known tokens, plus the custom stylesheet.
7. **R7 Custom stylesheet safety.** Admins still supply arbitrary CSS. It is served as `text/css` from the R6 route, never inlined in HTML, so it cannot break out of a `<style>` tag.
8. **R8 Layout.** `AppLayout.vue` with sidebar nav, Party dropdown (manager-only links gated on `canManage`), admin dropdown (Dashboard, Users, Settings, Pulse, Horizon; Telescope is gone after E3), Profile, Logout and impersonation banner. It has page title and breadcrumb slots. A `GuestLayout.vue` covers login. A `BareLayout.vue` covers TV, YouTube player and popup pages, for E8.
9. **R9 Shared kit** (`resources/js/Components`, `resources/js/Composables`):
   - `TrackRow`: album art, name, artists, duration, explicit badge, an in-Queue state with Score and Vote, and a request button with allowed/reason. It is ported from `SearchResult.vue` and accepts the E6 `TrackAnnotation` (a `Track` plus its eligibility, as produced by `TrackEligibility`) via a JSON resource, not the legacy `toSearchResult()` array. Album and artist names render as links when the caller passes a route; E8 fills these in.
   - `TrackRow` also has a decoration slot (display name override, label, CSS class, accent) for mod decorations, a "needs approval" state for a track that would be flagged, and an "awaiting approval" state for the member's own Flagged Request, with a withdraw action. E8 wires these to real data.
   - `TrackList` (paginated), `Pagination`, `EmptyState`, `ConfirmDialog` and `Toast`/flash.
   - `useEcho()`: one Echo instance built from the shared config, with private and public channel helpers that unsubscribe in `onBeforeUnmount`, and a reconnect callback hook for state resync.
   - `useRequestTrack(party)`: posts a Request and returns status and reason. It is the only request path in the new UI.
   - `useCountdown`/`useProgress(PlaybackState)`: an interval-safe progress timer anchored on `sampledAt`.
10. **R10 Pilot page.** The pilot is the Party Search page. Convert it (`parties/{party}/search`, currently `parties/search.blade.php`) to Inertia using `AppLayout`, `TrackList`, `TrackRow` and `useRequestTrack`. It keeps the same URL, query parameters, pagination, eligibility reasons and behaviour. The Blade view is deleted once the Inertia page is live.
11. **R11 Routing in JS.** Inertia pages use `laravel/wayfinder` for routes. Hard-coded URL strings are not allowed in new components.
12. **R12 Tooling.** The front end is linted (ESLint plus Prettier, or the project's chosen equivalent) in CI. Component tests use Vitest with Vue Test Utils for the shared kit. The E2 CI gains a `npm run build` and test step.
13. **R13 TypeScript.** All new Inertia code (pages, layouts, components, composables) is TypeScript. The existing Vue components (`Player`, `Next`, `Upcoming`, `SearchResult`, `TvPlayer`, `YouTubePlayer`) and `resources/js` entry files are converted to TypeScript without changing behaviour. `vue-tsc` type-checks in CI. An ADR records the TypeScript and Wayfinder adoption.

## Acceptance criteria

- A Pest feature test asserts the search route returns an Inertia response (`assertInertia`) with the expected component and props, for a member, a banned member and a guest redirect.
- Vitest covers `TrackRow` states (allowed, blocked with reason, needs approval, awaiting approval, already in Queue, explicit, decorated) and `useEcho` cleanup on unmount.
- `vue-tsc` passes in CI with every component in `resources/js` written in TypeScript, and the existing components behave as before on the local deployment.
- Side-by-side screenshots of the Search page, the Queue page (Blade) and the admin themes page on the local deployment versus v2 (`musicparty-old…`) show the same layout, colours and navigation, accepted by the user.
- Switching the active theme changes token colours on both a Blade page and the Inertia pilot without a rebuild. A custom stylesheet applies on both.
- `php artisan config:cache` followed by loading any page still connects to Reverb.
- `npm run build` passes in the production Dockerfile, and there are no console errors on the pilot or Queue pages.

## Functionality that must be retained

- Every Blade page works and looks like v2 after the Tabler upgrade, including the TV and YouTube pages and their public guest access.
- Active-theme selection, dark mode, the admin theme create/edit/delete pages and the read-only themes.
- Search behaviour: the Spotify Search account, pagination, in-Queue marking, all eligibility reasons and requesting from results.
- Navigation entries and their visibility rules (manager-only Party links, the admin menu, the impersonation unimpersonate link).
- Realtime updates on all existing pages.

## Suggested ticket slices

0. **E7-0** Migrate to the streamlined Laravel skeleton (#348). This redoes E3's #304, whose work never reached `feature/v3-rewrite`. blocked_by: none. Every other E7 ticket is blocked by it.
1. **E7-1** Install Inertia, TypeScript (`tsconfig`, `vue-tsc`) and Wayfinder, Vite entry points, root view and middleware with shared props except the theme, ADR (R1-R3, R11, R13 tooling). blocked_by: E7-0.
2. **E7-2** Front-end lint (ESLint, Prettier) and Vitest tooling, plus lint, type-check and test steps in CI (R12). blocked_by: E7-1.
3. **E7-3** Tabler upgrade and Blade visual fixes (R4). blocked_by: E7-0.
4. **E7-4** Token stylesheet mapped onto Tabler (R5). blocked_by: E7-3.
5. **E7-5** Themes as token sets: cached active-theme resolver and removal of the seating CSS first, then the migration, served theme CSS, theme shared prop and admin form (R2 theme, R6, R7). blocked_by: E7-1, E7-4.
6. **E7-6** `AppLayout`, `GuestLayout` and `BareLayout` matching v2 (R8). blocked_by: E7-1, E7-4.
7. **E7-7** Track display kit: `TrackAnnotation` JSON resource and TypeScript type, `TrackRow` (all states), `TrackList`, `Pagination`, `EmptyState`, with Vitest (R9). blocked_by: E7-2, E7-6.
8. **E7-8** Composables and feedback kit: `useEcho`, `useRequestTrack`, `useProgress` with a `PlaybackState` JSON contract, `ConfirmDialog`, `Toast`/flash, with Vitest (R9). blocked_by: E7-2, E7-6.
9. **E7-9** Pilot: Party Search page in Inertia (R10). blocked_by: E7-5, E7-7, E7-8.
10. **E7-10** Convert the existing Vue components and JS entry files to TypeScript, type-only (R13). blocked_by: E7-1, E7-2.

## Risks and open questions

Implementation-level; settle when tickets are written.

- **Theme token schema.** Which tokens are editable in admin, and whether themes may override the font. Keep the list small at first. Whamageddon's `css_classes` styling stays as it is until E8's decorations.
- **The Tabler upgrade** may shift Blade layouts in ways E9 will redo anyway. Fix only regressions, not polish.
- **Coexistence cost.** Two entry points and two layouts until E9. Keep the Blade layout reading the same token CSS, so the look never diverges.
- **`TrackRow` and `useEcho` API.** E8 and E9 depend on them. Changing their API after E7 needs a coordinated ticket in whichever epic changes it.
