# Music Party v2 reference screenshots

Captured from the v2 app (`musicparty-v2`) running locally on a throwaway copy with SQLite and seeded fake data
(fictional songs/artists, generated SVG cover art and avatars). Spotify was never contacted: search results were
stubbed, and the now-playing status was seeded. Chrome headless, 1440x900 (desktop) and 390x844 (mobile),
`-light` (active Default theme, `dark_mode` off) and `-dark` (same theme with `dark_mode` on, so `<body data-bs-theme="dark">`). Every dark image was checked to differ from its light pair. Full-page captures except the TV screen (viewport only).

**Desktop shots are the v3 reference; mobile shots are not.** v2's mobile layout is not fit for purpose. v3 mobile is redesigned mobile-first in the same visual style (see the theming spec's mobile-first layout requirement). The mobile shots are kept only as a record of what to move away from.

File pattern: `<page>-<desktop|mobile>-<light|dark>.png`

| Page | Shows |
|---|---|
| `login-*` | Login page: provider buttons (Discord, Steam, Twitch) beside the cover image. |
| `home-*` | Home (`/`) as a guest: sidebar nav plus profile card. Empty otherwise. |
| `party-queue-*` | Party page (`/parties/FRI123`) as a guest: now-playing banner with thumbs rating, "Up Next", search box, upvote/downvote queue (own votes highlighted). Bottom "Loading upcoming songs..." is the infinite-scroll loader. |
| `party-search-*` | Search results: Add button and popularity stars for new tracks, vote controls for tracks already queued. Results are canned (stubbed). |
| `played-history-*` | Songs list (`/parties/{code}/songs?type=spotify`, "Sent to Spotify"), i.e. played history. Captured as the party owner: v2 only exposes this to managers, so it also shows the owner nav entries. |
| `tv-*` | TV screen (`/parties/{code}/tv`): large now-playing, progress, Next, party name/code, QR code. Viewport only. The TV page ignores the theme (always black background), so light and dark are visually the same design and differ only in the playback timer. |
| `party-list-mobile-*` | Party list as v2 presents it: the sidebar nav (opened on mobile) listing the user's parties. Mobile only; desktop sidebar is visible in every other desktop shot. |

## Not rendered
- A standalone party list page: v2 has no `parties.index` view (the route has no controller method), so the sidebar nav is the only party list.
- Admin pages (skipped by request).
- Live updates (Reverb) were off, so nothing updates in real time. The progress bar position is a snapshot.
