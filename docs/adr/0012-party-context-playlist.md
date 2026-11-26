# Party Context Playlist: play every track inside one empty, host-owned playlist

Music Party starts tracks inside a Context Playlist: one private, empty Spotify playlist named "Music Party Context" on the host's account. This reintroduces a party-owned playback context, reversing its removal in the v1 rewrite (94fc1bb5). Spotify then reports the Party as the playback context, which lets the Dashboard tell "playing in the party" from "playing something else" by comparing `PlaybackState::$contextUri` with the playlist's context URI.

**One playlist per host account.** It is found by name on first use, created if missing, and its id is stored on the Party (`context_playlist_id`). Changing the Party's host resets the id, because the playlist belongs to the old host. The id can be edited in the Party settings, but only to a playlist the host owns.

**Emptied every 15 minutes.** `party:contextplaylist` dispatches `EmptyContextPlaylist` for each active Party with an id. The job clears the playlist if it has items, and forgets the id if the host no longer owns it. This is why only host-owned playlists are accepted: a pasted playlist would be emptied.

**Every in-app play path uses `playInContext`.** That covers Start Playback, Play now, forced playback, and the control `play` action. The control `play` action tries the Web API in context first even when a pushed Player (Encore, Web Player) is fresh, because both are Connect devices; the pushed `play` command is the fallback on `NoActiveDeviceException`. Pause, next, previous and seek keep using the pushed transport.

**Known limit.** The spike found that an empty context does not stop Spotify autoplay once the context runs out. The empty context was chosen anyway, and `QueueSelector`'s one-ahead queueing remains what controls what plays next.

**Contract.** `MusicProvider` gains the playlist and context methods (`ownedPlaylist`, `findOwnedPlaylistByName`, `createPrivatePlaylist`, `clearPlaylist`, `playlistContextUri`, `contextName`) and `ProviderPlayback` gains `playInContext`. These extend ADR 0001 rather than superseding it: playlists stay on `MusicProvider` and playback stays on `ProviderPlayback`.

**Dashboard access.** The Dashboard and its playback endpoints are gated by `PartyPolicy::update`: the union of "can moderate" and "can control playback". Approve, reject and delete use `UpcomingSongPolicy`, which is `Party::canBeManagedBy()`, so a site admin who is not a member can open the page but not moderate from it.
