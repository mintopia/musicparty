# Remove all v1/v2 code; no parallel legacy paths

The v3 rewrite left the v1/v2 system running next to it: the `upcoming_songs`, `votes`, `played_songs` and `songs` models, the `/api/v1/.../upcomingsongs` API, `app/Services/*`, observers, about 560 lines of Spotify-SDK methods on `Party` and 150 on `User`, unauthenticated webhooks, `/proxy`, `party:refreshaccesstokens` and the `jwilsson/spotify-web-api-php` SDK. The 2026-10-10 audit found that these paths skip v3's rules (membership, Bans, Party state, Blocklist) and include the worst security findings. Some v3 features also quietly depend on them. The only caller of `AppendPlayToHistory` is the legacy `Party::addTracksToQueue`, `FallbackPlaylistGate` reads the legacy `played_songs` table, and the playlist picker writes the v2 `backup_playlist_id` column.

Decision:
- No v1/v2 code stays, and nothing is deprecated or feature-flagged. Each legacy path is deleted.
- Anything still needed is rebuilt properly in v3 first, inside its bounded context, with tests (for example the Fallback and History Playlists). Only then is the legacy code it replaced deleted. A v3 test may not depend on a legacy model or table.
- Legacy tables and columns are dropped with forward migrations. There is no data migration, which is consistent with the v3 rewrite (no v2 data carried over).
- Packages used only by legacy code are removed in the same change, and so are their config, published assets and Compose services.
- Two v3 features that duplicate each other are cut back to one: `Rating` is kept over `PlayRating`, and `party_log_entries` over `party_logs`.

Consequences: v1/v2 API and webhook URLs return 404. Operators must back up before running the drop migrations. The PHPStan baseline, `_ide_helper_models.php`, the OpenAPI document and the AsyncAPI document are regenerated or edited to match.
