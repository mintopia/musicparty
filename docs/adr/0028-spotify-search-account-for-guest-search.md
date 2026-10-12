# Reinstate the Spotify Search account for guest search

v2 registered a second Spotify OAuth app, "Spotify Search" (provider code `spotifysearch`, its own client id and secret, never usable for login). On the `/spotify` page the Host linked their Spotify account twice, once for control and once for search. `SpotifySearchService` searched with the Host's search account and fell back to the control account. The archived v3 proposal and the music-provider spec removed this: search uses instance-level client credentials only, and only the Host links a Provider account (triage diff-8). The owner ruled to restore it, superseding that removal.

The reason is quota. Spotify rate-limits by client application, so a busy Party's guest search on the control app can stall playback control. A separate application for search keeps them apart.

Decision:
- Admins configure a `spotifysearch` social provider (client id and secret). It is never offered for login.
- The Host links a Spotify Search account from the restored Link Spotify Account page. It is stored as a linked account under that provider, with the same encrypted token storage and refresh as the control link.
- Search for a Spotify Party uses the Host's Search account token when it is linked and valid, else the Host's control account (as in v2), else the instance client credentials (for Hosts with no Spotify link).
- The Spotify backoff in ADR-0021 is keyed per Spotify client application, so the search app and the control app back off independently.
- The Search link is optional. Create Party requires only the control link that the Player needs, even when the admin has enabled the Spotify Search provider (the owner chose this over v2's both-links gate).

Consequences: the Spotify code carries three token sources for search. A Host who links a Search account keeps guest search off the control application's quota, so search can no longer cause a 429 that pauses their playback control. A Host who does not link one shares the control quota, as in v2. The music-provider spec's statement that search uses instance credentials only is replaced.
