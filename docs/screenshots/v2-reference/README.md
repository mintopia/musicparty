# E9 v2 reference screenshots

Baseline for each E9 conversion ticket's side-by-side review (PRD `docs/specs/E09-ui-migration.md`, req 2).

Captured 2026-10-13 from the local dev deployment (`https://musicparty.sailing-coyote.ws.cloudagent.mintopia.net`), which serves the integration branch at `d2f77b44`. Pages listed below are still Blade and render the v2 look. 1440x900 viewport, full page, headless Chrome 153, Laravel debugbar hidden. Theme: Default. Party `NPRN` (Testing Party) with a playing track and queue.

Roles: `guest`, `member` (plain party member), `manager` (party owner with `create-party`), `admin` (site admin). A page is stored under a role only when that role can open it and the page differs from the role above it; a missing file means the role gets 403 or a redirect. Member gets 403 on songs, users, edit, moderation, party create and `linkedaccount-delete`. Guest gets a login redirect on `party-show` and `home`, so only `login` and the three public player pages are stored. `member/home.png` and the other `home.png` files show the redirect target (the party page).

## Page to file map

| PRD page | File (under role dir) | Roles captured |
|---|---|---|
| `parties/show` (+ `_player`) | `party-show.png` | member, manager, admin |
| `parties/tv` | `party-tv.png` | guest, member, manager, admin |
| `parties/youtube` | `party-youtube.png` | guest, member, manager, admin |
| `parties/ytplayer` | `party-ytplayer.png` | guest, member, manager, admin |
| `upcomingsongs/index` | `party-songs-index.png` | manager, admin |
| `upcomingsongs/show` | `party-songs-show.png` | manager, admin |
| `home/home` | `home.png` | member, manager, admin |
| `users/login` (`layouts/login`) | `login.png` | guest |
| `users/signup` | `signup.png` | member |
| `users/spotify` | `spotify.png` | admin (others redirect to profile) |
| `users/profile` | `profile.png` | member, manager, admin |
| `users/edit` | `profile-edit.png` | member, manager, admin |
| `users/linkedaccounts/delete` | `linkedaccount-delete.png` | admin |
| `parties/create` | `party-create.png` | manager, admin |
| `parties/edit` (+ `_form`) | `party-edit.png` | manager, admin |
| `partymembers/index` | `party-members-index.png` | manager, admin |
| `partymembers/show` | `party-members-show.png` | manager, admin |
| `partymembers/edit` | `party-members-edit.png` | manager, admin |
| `partymoderation/index` | `party-moderation-index.png` | manager, admin |
| `partymoderation/create` | `party-moderation-create.png` | manager, admin |
| `partymoderation/edit` | `party-moderation-edit.png` | manager, admin |
| `partymoderation/delete` | `party-moderation-delete.png` | manager, admin |
| `admin/home/dashboard` | `admin-dashboard.png` | admin |
| `admin/settings/index` | `admin-settings.png` | admin |
| `admin/socialproviders/edit` | `admin-socialprovider-edit.png` | admin |
| `admin/themes/create` | `admin-theme-create.png` | admin |
| `admin/themes/edit` | `admin-theme-edit.png` | admin |
| `admin/themes/delete` | `admin-theme-delete.png` | admin |
| `admin/users/index` | `admin-users-index.png` | admin |
| `admin/users/show` | `admin-users-show.png` | admin |
| `admin/users/edit` | `admin-users-edit.png` | admin |
| `admin/users/delete` | `admin-users-delete.png` | admin |
| `admin/linkedaccounts/delete` | `admin-linkedaccount-delete.png` | admin |

Not captured: impersonation has no page of its own (it is a redirect from `admin/users/{user}/impersonate`; the banner appears on the converted pages). Shared partials (`_pagination`, `_sortheader`, `_searchtextfield`, `_searchselectfield`, `_providersconfig`, `_theme`) are visible inside the pages above: `_pagination`/`_sortheader`/`_searchtextfield` in `admin-users-index`, `_searchselectfield` in `party-members-index`, `_theme` on every page.

Dev data used for the capture: users `Ref Manager` (id 2) and `Ref Member` (id 3) were added to party NPRN, plus one moderation rule and a theme `Reference Theme` (id 2), all in the dev database only.

## Remaining Blade page inventory (after E8)

Controllers that still `return view(...)`, 33 views. Entry file is `resources/js/app.ts`; `SearchResult.vue` is dead code; `Pagination.vue` already exists. Already on Inertia: `parties.player`, party mods, flagged requests, admin mods, the shell (`resources/js/pages/Parties`, `Admin`, `Shell.vue`).

- Party guest: `parties.show`, `parties.tv`, `parties.youtube`, `parties.ytplayer`, `upcomingsongs.index`, `upcomingsongs.show` (`PartyController`, `UpcomingSongController`)
- Auth and account: `home.home`, `users.login`, `users.signup`, `users.spotify`, `users.profile`, `users.edit`, `users.linkedaccounts.delete` (`HomeController`, `UserController`, `LinkedAccountController`)
- Party management: `parties.create`, `parties.edit`, `partymembers.{index,show,edit}`, `partymoderation.{index,create,edit,delete}`
- Admin: `admin.home.dashboard`, `admin.settings.index`, `admin.socialproviders.edit`, `admin.themes.{create,edit,delete}`, `admin.users.{index,show,edit,delete}`, `admin.linkedaccounts.delete`
- Layouts and partials still Blade: `layouts/app`, `layouts/login`, `partials/_pagination`, `_sortheader`, `_searchtextfield`, `_searchselectfield`, `_providersconfig`, `_theme`, `_pusher-config`, `parties/_player`, `parties/_form`, `parties/_encore`, plus `partymoderation/_form`, `admin/themes/_form` and the admin breadcrumb partials
- Vendor, stays: `vendor/pulse/dashboard`
