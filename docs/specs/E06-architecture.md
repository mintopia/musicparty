# E6 Architecture

Goal: break up the `Party` and `User` god classes into focused services. Then put Spotify behind a Music Provider contract, so that E7 and E8 build on stable seams instead of on `Party` and raw Spotify objects.

- Status: Draft
- Wave: 5
- blocked_by: E4 Deployment, E5 Reliability
- Downstream: E7 UI foundation, then E8 Mods, Players & Browsing. The seams E8 relies on are delivered here, in [Contract surface](#contract-surface); E8 extends them in its foundation slices.

## Problem

- `app/Models/Party.php` is 800 lines. It holds:
  - playback sync (`updateState` 128-153, `updateCurrentSong` 562-601, `getStatus` 239)
  - Spotify transport (281-336)
  - Queue selection (`addTracksToQueue` 356-462)
  - Fallback Playlist backfill (603-680)
  - the mod-settings store (155-219)
  - membership (485), authorization (682) and serialization (221)
  - trust-score linear algebra (713-799)
- `app/Models/User.php` builds the Spotify client and refreshes tokens (141-178), and caches devices, playlists and status. `SpotifySearchService::getApi()` (24-61) duplicates the token logic.
- Party calls `$this->user->getSpotifyApi()` about 15 times. Observers hide side effects:
  - `PartyObserver::created` calls `updateState()`, which calls Spotify inside the model save.
  - `saved` fetches the Fallback Playlist synchronously.
- There is no music abstraction. `songs.spotify_id` is the only identity. `"spotify:track:{id}"` is hard-coded (`Party.php:292-309, 544-547`). Raw Spotify `stdClass` objects flow through `RequestCheckService`, `PartyModeration::match` and `SpotifySearchService::augmentResults`. The API request field is `spotify_id` (`UpcomingSongRequest.php:25`, `Api/V1/UpcomingSongController.php:56-81`). Spotify quirks such as relinking, `is_playable`, `CONTEXT_PLAYLIST_TRACK` (`RequestCheckService.php:13`) and the queue-repeats-context hack (`Party.php:363-374`) live in the domain.
- Search covers tracks only (`SpotifySearchService.php:63-77`). There is no album or artist lookup, which E8 needs.

## Goals

- Party and User keep persistence, relations and small invariants only. Each extracted responsibility is an injectable service with its own tests.
- A `MusicProvider` contract (search, Track metadata, playlists, browse), a Spotify implementation and a Fake implementation for tests (ADR 0001).
- One Music Provider per Party. Tracks are identified by `provider_id` + `provider_track_id`, which replaces `songs.spotify_id`.
- No hidden side effects in observers. Spotify calls happen only in explicit actions or jobs.
- New code uses glossary terms (Request, Queue, Played Track, Sent to Player).

## Non-goals

- Tidal or any second real Music Provider. Mixed-provider Queues.
- Push-based Players, the Web Player rework, Encore, the Mod contract, Flagged and Scheduled Requests, and album and artist pages (all E8).
- Renaming models or tables wholesale. Renames happen only in code a ticket already rewrites.
- Changing behaviour. This is a refactor guarded by E2's characterization tests, plus the E5 fixes.

## Contract surface

These are delivered by E6 and relied on by E7 and E8. Names are proposals. They follow the existing `app/Services` and `app/Services/Contracts` layout, so no new base folders are needed.

**Value objects** (`App\Services\Music\Data`, immutable, readonly):
- `Track`: providerId, id, name, durationMs, explicit, isPlayable, `album` (AlbumRef), `artists` (list of ArtistRef), linkedFromId (nullable).
- `AlbumRef`: id, name, imageUrl. `ArtistRef`: id, name.
- `Album`: AlbumRef plus artists, releaseDate, totalTracks and `tracks` (list of Track).
- `Artist`: ArtistRef plus imageUrl.
- `Playlist`: id, name, ownerName, trackCount.
- `Page<T>`: items, total, page, perPage.
- `PlaybackState`:
  - isPlaying, trackId, linkedFromId, durationMs
  - progressMs plus `sampledAt` (when the progress was measured, not when it was received)
  - shuffle, repeat, deviceId, deviceName
  - `source` enum: `Polling`, `Webhook`. E8 adds Web Player and Encore cases, optional upcoming tracks and connection status.

**`MusicProvider`** (`App\Services\Contracts\MusicProvider`):
- `id(): string` (e.g. `spotify`, `fake`)
- `searchTracks(query, page, perPage): Page<Track>`
- `getTrack(id): ?Track`, `getTracks(ids): list<Track>`
- `getAlbum(id): ?Album`, `getArtist(id): ?Artist`
- `getArtistTopTracks(id): list<Track>`, `getArtistAlbums(id, page, perPage): Page<AlbumRef>`
- `getPlaylistTracks(playlistId): list<Track>`, `appendToPlaylist(playlistId, tracks)`, `listPlaylists(): list<Playlist>`

**`ProviderPlayback`** (`App\Services\Contracts\ProviderPlayback`). This is the remote-control half that the Music Provider's account offers. Polling uses it, as does any server-side transport:
- `currentPlayback(): ?PlaybackState`
- `sendToPlayer(Track)`
- `play(?deviceId, ?Track)`, `pause()`, `next()`, `previous()`, `seek(ms)`
- `devices(): list<Device>`

**Resolution:** `MusicProviderFactory::forParty(Party, Purpose)`, where `Purpose` is `Search` or `Control`. It resolves the Party's `provider_id` through a registry of Music Providers and picks credentials: the owner's control account, or the Spotify Search account with fallback to the control account, as today.

**Services:**
- `PlaybackSync::apply(Party, PlaybackState)` is the single entry point that updates the current track, records the Played Track, tops up the Queue and broadcasts. E8 feeds pushed states into it.
- `QueueSelector` picks the next Request. It computes each Request's effective Score in one overridable step, which is E8's hook for score modifiers. E8 adds a candidate-filter step and an add-Request hook.
- `RequestRules` is an ordered list of `RequestRule` objects over (Party, Member, Track). Each returns allow or reject-with-reason. E8 makes the outcome three-way (allow, flag, reject) and appends mod and AI stages.
- `TrackEligibility::annotate(Party, Member, list<Track>)` returns the in-Queue, Score, Vote, requester, allowed and reason data that search shows today. E8 reuses it on album and artist pages.
- `ModSettingsStore` reads and writes per-Party mod settings with one query per Party, and no stale per-instance cache.
- `FallbackPlaylist` handles backfill and caching. `SpotifyGateway` is the only place that builds Spotify clients and refreshes tokens, keeping E5's lock and backoff.

## Requirements

1. **R1** A `SpotifyGateway` builds clients per LinkedAccount. `User::getSpotifyApi()` and `SpotifySearchService::getApi()` are removed, and `User` holds no Spotify client or session state. The E5 refresh lock, 429 backoff and private-channel token broadcast move with it unchanged.
2. **R2** `ModSettingsStore` replaces `Party::loadModSettings/getModSetting*/setModSetting/deleteModSetting`. `setModSetting` creates missing values instead of silently doing nothing (facts §5).
3. **R3** `FallbackPlaylist` replaces `backfillUpcomingSongs`/`getBackupPlaylistTracks`. Changing `backup_playlist_id` queues a refresh job rather than fetching inside `saved`.
4. **R4** `MusicProvider` and `ProviderPlayback` exist as specified above, with `SpotifyMusicProvider` (over the gateway) and `FakeMusicProvider` implementations. The Fake has a deterministic in-memory catalogue and records the calls made to it. The container binds the Fake in `testing`.
5. **R5** All Spotify SDK objects are mapped to value objects at the provider boundary. Relinking, `is_playable`, local files, URIs and the context-playlist and queue-repeat quirks live only in the Spotify implementation.
6. **R6** `RequestRules` reproduces every current check and reason string from `RequestCheckService`. `TrackEligibility` reproduces `SpotifySearchService::augmentResults`. Search goes through `MusicProvider::searchTracks`.
7. **R7** `QueueSelector` reproduces weighted and random selection, `not_before`, `score_adjustment`, `fallback_override` and Fallback Playlist top-up. It sends Requests via `ProviderPlayback::sendToPlayer`.
8. **R8** `PlaybackSync` replaces `updateState`/`updateCurrentSong`/`getStatus`. Polling builds a `PlaybackState` from `ProviderPlayback::currentPlayback`. Playback Webhooks trigger a sync as they do today. Transport (`Api\V1\PartyController::control`, `party:force`) goes through `ProviderPlayback`.
9. **R9** Observers keep only pure model concerns. Party creation dispatches the initial sync explicitly, and no observer calls Spotify.
10. **R10 Schema.** Add `provider_id` and `provider_track_id` to `songs`, and the equivalent provider ids to `albums` and `artists`, each with a unique index on the pair. Backfill from `spotify_id` with `provider_id = 'spotify'`. Add `parties.provider_id`, non-null, default `spotify`. Id-based `PartyModeration` rules keep matching, because their ids are now read as provider ids of the Party's Music Provider.
11. **R11 API and front end.** The request payload and API resources expose `provider_track_id` instead of `spotify_id`, switched outright with no `spotify_id` alias. `SearchResult.vue`, `Player.vue` and `TvPlayer.vue` are updated in the same ticket.
12. **R12** After the schema slice, the `spotify_id` columns are dropped. Arch tests (Pest `arch()`) assert that `SpotifyWebAPI`, `spotify:track:` and Spotify SDK classes appear only under `App\Services\Music\Spotify` and the gateway.
13. **R13** Browse methods are implemented and cached for Spotify (album and artist metadata cached for at least 1 h) and present in the Fake.
14. **R14** Renames: new classes use glossary names. Existing classes are renamed only when a ticket rewrites them, and no table renames happen.
15. **R15 Provider registry and admin toggles.** Music Providers are registered by id (`spotify`, `fake`). Each has an enable toggle on the admin settings page. A disabled provider cannot be chosen for a Party. Spotify credentials stay in the database, where they are today.
16. **R16 Party provider choice.** The Party form offers the enabled Music Providers. The Fake provider is offered only when the app runs in the `local` environment, and is bound in `testing` as R4 says.

## Acceptance criteria

- The E2 characterization suite passes unchanged at every slice, apart from expectations E5 already corrected.
- Each new service has Pest unit tests against `FakeMusicProvider`. No test hits the network.
- `Party.php` no longer contains sync, selection, backfill, mod-settings or transport code, and no `getSpotifyApi` call exists anywhere.
- Arch tests from R12 pass. PHPStan level 8 passes on all new code with no baseline additions.
- A migration test backfills a seeded `spotify_id` dataset and proves Requests, Played Tracks, Votes and moderation rules still resolve.
- Every slice deploys on its own, and the local deployment plays, polls and accepts Requests after each one.

## Functionality that must be retained

Everything in E5's retained list, plus:
- Spotify Search account behaviour.
- Moderation rule types (name, artist, album, ids).
- VIP exemption from request limits, and downvote throttling.
- The History Playlist append and the device picker.
- `show_qrcode`, the TV and YouTube pages, and Whamageddon as it currently works.
- Trust-score code stays in place, untouched, for E8 to rework.

## Suggested ticket slices

1. **E6-1** Value objects and Spotify-to-value-object mappers. blocked_by: none.
2. **E6-2** `SpotifyGateway`, and strip the Spotify client from `User` and `SpotifySearchService` (R1). blocked_by: none.
3. **E6-3** `ModSettingsStore` (R2). blocked_by: none.
4. **E6-4** `MusicProvider` and `ProviderPlayback` contracts, the Spotify and Fake implementations and the factory (R4, R5). blocked_by: E6-1, E6-2.
5. **E6-5** `FallbackPlaylist` and the queued refresh (R3). blocked_by: E6-4.
6. **E6-6** `RequestRules` and `TrackEligibility`, with search via the provider (R6). blocked_by: E6-4.
7. **E6-7** `QueueSelector` (R7). blocked_by: E6-4, E6-5.
8. **E6-8** `PlaybackSync`, transport via `ProviderPlayback`, and observer cleanup (R8, R9). blocked_by: E6-7.
9. **E6-9** Browse methods for album, artist, top tracks and artist albums, with caching (R13). blocked_by: E6-4.
10. **E6-10** Provider identity columns, the backfill, `parties.provider_id`, and the API and front-end switch (R10, R11). blocked_by: E6-6, E6-8.
11. **E6-11** Drop `spotify_id`, add arch tests, final Party/User slimming and renames where touched (R12, R14). blocked_by: E6-10, E6-3.
12. **E6-12** Provider registry, admin enable toggles and the Party provider choice with the local-only Fake (R15, R16). blocked_by: E6-10.

## Risks and open questions

Implementation-level; settle when tickets are written.

- **Changing a Party's provider.** Only Spotify is real, so switching an existing Party's Music Provider matters only locally. The E6-12 ticket decides whether the choice locks after creation.
- **Migration size.** The backfill on large `songs` tables should be chunked, and the column drop held one deploy behind the backfill.
- **`device_id` and `history_playlist_id`.** These remain Spotify-shaped columns on `parties`. E8 decides the device's fate, and the playlist ids are read as provider ids.
- **`PlaybackState.sampledAt`** fixes the progress drift caused by `updatedAt` (facts §4). Display components change only in E8 or E9.
