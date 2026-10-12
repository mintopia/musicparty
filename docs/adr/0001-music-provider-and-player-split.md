# Separate Music Provider from Player, one Music Provider per Party

`Party` currently fuses the Spotify catalogue with watching playback, and `songs.spotify_id` is the only identity a track has. We split the two roles. A **Music Provider** owns search, track metadata and playlists. A **Player** reports and controls what is actually playing. Tracks are identified by `provider_id` + `provider_track_id`, so a backend such as Tidal can be added later without touching the Party, Request and voting logic.

A Party uses exactly one Music Provider. Mixed-provider Queues are out of scope, because they would need cross-provider track matching.
