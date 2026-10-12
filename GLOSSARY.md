# Music Party

A collaborative party playlist: guests log in, search for tracks, request them and vote, and the Party plays the result.

## Language

### Requests

**Request**:
A track a Party member asked for that has not played yet.
_Avoid_: Upcoming song, queue entry, suggestion

**Queue**:
A Party's waiting Requests, in the order they are likely to play.
_Avoid_: Upcoming, playlist

**Sent to Player**:
The point at which a Request is handed to the Player to play next. It is still a Request until it plays.
_Avoid_: Queued

**Flagged Request**:
A Request held back for a Party manager to approve or reject. It exists only in Parties that have flagging enabled.
_Avoid_: Pending, moderated request

**Scheduled Request**:
A Request that a Party owner or moderator has set to play as soon as possible after a given time.
_Avoid_: Timed request, booking

**Played Track**:
A history entry recording that a track played in a Party.
_Avoid_: Played song, history item

### Music and playback

**Music Provider**:
The music service a Party draws its catalogue from, for search, track metadata and playlists (e.g. Spotify, potentially Tidal).
_Avoid_: Backend, service, source

**Player**:
Whatever reports what is actually playing for a Party. A Party never picks its Player; whichever one sends playback updates is the Player.
_Avoid_: Device, provider

**Polling**:
A per-Party switch that has Music Party ask the Music Provider what is playing, rather than waiting to be told.
_Avoid_: Polling Player, fallback

**Playback Webhook**:
An HTTP endpoint that any external player calls to tell Music Party that playback changed.
_Avoid_: Soloist webhook, librespot webhook

**Web Player**:
Music Party's own built-in player page. It plays through the browser and reports playback changes back to Music Party.
_Avoid_: Spotify player, browser player

**Encore**:
Our own self-hosted Spotify Connect speaker, built around Soloist. It streams playback events to Music Party. Formerly called Music Party Soloist.
_Avoid_: Soloist proxy, Music Party Soloist

**Soloist**:
Spotify's headless Spotify Connect client, which Encore wraps.
_Avoid_: Using "Soloist" to mean Encore
