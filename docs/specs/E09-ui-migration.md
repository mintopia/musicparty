# E9 UI migration

Goal: convert every remaining Blade page to Inertia and Vue on the E7 foundation, then remove the Blade layouts and assets, keeping the v2 look.

- Status: Draft
- Wave: 8 (last)
- blocked_by: E8 Mods, Players & Browsing (and so E7 UI foundation)

## Problem

- The UI is Blade views with six Vue SFCs mounted through `createApp` in `resources/js/app.js` (`Player`, `Next`, `Upcoming`, `SearchResult`, `TvPlayer`, `YouTubePlayer`). After E7 and E8, Inertia and Blade pages coexist with two layouts, two asset paths and two ways to get realtime config.
- Realtime config is still injected inline as `window.pusherConfig` in `layouts/app`, `parties/tv` and `parties/ytplayer` (built from `config()` since E4), separate from the Inertia shared props.
- The existing components carry known bugs: `updateProgressBar` never resets `intervalId` (`Player.vue:169-171`, `TvPlayer.vue:59-61`), so the progress timer never restarts after a pause; `beforeDestroy` is the Vue 2 hook name, so cleanup never runs; `Player.vue:52` reads `state.current.rating` without a null check; there is no resync of state after an Echo reconnect.
- Theme CSS is rendered through `partials/_theme`.

## Goals

- Every user-facing page is an Inertia page using the E7 shell, layout, Tabler version and design tokens.
- Remove Blade layouts, partials and legacy assets once nothing uses them.
- The UI keeps the v2 look, evolved onto the new Tabler and tokens.

## Non-goals

- New features or changed behaviour. Each page does what its Blade version does.
- Vendor dashboards: Horizon and Pulse (`vendor/pulse/dashboard.blade.php`, Livewire) keep their own UIs.
- Pages already built in Inertia by E7 (the Search pilot) or E8 (Web Player, Mods screens, Flagged Requests queue, album and artist pages).

## Pages in scope

Converted in this order, guest-facing first and admin last, minus any already converted:

1. Party guest pages: `parties/show`, `parties/_player`, `parties/tv`, `parties/youtube`, `parties/ytplayer`, `upcomingsongs/index`, `upcomingsongs/show`.
2. Auth and account: `home/home`, `users/login`, `users/signup`, `users/spotify`, `users/profile`, `users/edit`, `layouts/login`.
3. Party management: `parties/create`, `parties/edit`, `parties/_form` (including the Party timezone, country, Music Provider, flagging and Scheduled Request settings added by E6 and E8), `partymembers/*`, `partymoderation/*`.
4. Admin: `admin/home/dashboard`, `admin/settings/index`, `admin/socialproviders/edit`, `admin/themes/*`, `admin/users/*` (including impersonation), `admin/linkedaccounts/delete`.
5. Shared partials replaced by Vue components: `_pagination`, `_sortheader`, `_searchtextfield`, `_searchselectfield`, `_providersconfig`, `_theme`.

## Requirements

1. Each page in scope renders through Inertia with the E7 layout. Its route name, URL, authorization and validation are unchanged.
2. Before any page is converted, the v2 deployment (`musicparty-old…`) is deployed and checked, and a reference screenshot of every page in scope is captured from it, for guest, member, manager and admin where they differ. Each converted page matches its reference in layout, content and controls, evolved onto the new Tabler and tokens. When a mockup exists, the page matches the mockup.
3. Existing Vue components may be rewritten, in TypeScript. Rewrites fix the known bugs: the progress timer restarts after pause, cleanup uses `beforeUnmount`, a missing current track does not throw, and state is resynced after an Echo reconnect.
4. Realtime config comes from one source shared by all Inertia pages. No view reads `env()`, and no inline `window.pusherConfig` remains.
5. Themes keep working: the selected theme's token values apply on every page, and its custom stylesheet still loads. Custom stylesheets written against v2's Tabler class names may break, which is accepted; the built-in look of every page must still match v2.
6. Flash messages, validation errors, pagination, sorting and search filters behave as they do in Blade.
7. Guest access stays as it is: the TV, YouTube and ytplayer pages and the public Party view work without login, with the E1 fixes intact.
8. Admin impersonation, and leaving impersonation, work from the converted admin pages.
9. When a group's last Blade page is converted, its unused Blade views, partials, layout and assets are deleted in the same ticket. At the end, no Blade layout remains except what vendor packages need and any server-only templates (for example mail).
10. Feature tests assert each converted route returns the expected Inertia component and props, for guest, member, manager and admin where relevant.

## Acceptance criteria

- No route in `routes/web.php` returns a Blade view, except vendor dashboards.
- `resources/views/layouts/` and the converted page directories are gone. `resources/js/app.js` no longer mounts components with `createApp` into Blade.
- Inertia page tests pass for every converted route.
- A side-by-side screenshot review of each page group against the v2 deployment is signed off by the user before that group's ticket closes.
- `npm run build` succeeds with no references to removed assets.

## Functionality that must be retained

- Everything the Blade pages do today: party create/edit/show/search, requesting and voting, ratings, Queue and history views, TV and YouTube pages, member management (VIP, banned, roles), moderation rules, request limits, themes, admin settings, social provider config, user admin and impersonation, multiple social logins, linking accounts and email account merge.

## Suggested ticket slices

1. **Inventory, v2 reference capture and shared components**: list the Blade pages still remaining after E8; capture the v2 reference screenshots (req 2); build Vue replacements for the shared partials (pagination, sort header, search fields). blocked_by: none.
2. **Party guest pages**, with rewritten `Player`, `Next`, `Upcoming`, `SearchResult` and the bug fixes. blocked_by: 1.
3. **TV, YouTube and ytplayer pages**, with rewritten `TvPlayer` and `YouTubePlayer`. blocked_by: 1.
4. **Auth and account pages**, then remove `layouts/login`. blocked_by: 1.
5. **Party management pages** (create, edit, members, moderation). blocked_by: 2.
6. **Admin pages** (dashboard, settings, social providers, themes, users, linked accounts). blocked_by: 1.
7. **Remove Blade layouts and legacy assets**: `layouts/app`, `_theme`, the `createApp` mounting, `window.pusherConfig`, unused Sass. blocked_by: 2, 3, 4, 5, 6.

## Risks and open questions

Implementation-level; settle when tickets are written.

- The TV page runs unattended on screens for hours. The rewritten components need a soak check for memory leaks and reconnects.
