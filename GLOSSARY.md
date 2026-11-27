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

**Fallback Playlist**:
The playlist a Party plays from when the Queue is empty. A track from it is a Fallback Track.
_Avoid_: Backup playlist, house playlist, fallback

**Party is running**:
The Party-level switch for whether the Queue is processed and Requests are accepted into it. One label on Create Party and Settings.
_Avoid_: Active, Process the queue and requests

**Moderation**:
The area where a manager handles Flagged Requests, Rules and AI reviews. Its tabs are Flagged Requests, Rules and AI reviews.
_Avoid_: Rules (as the page name), Flagged (as a tab name)

**Scheduled Request**:
A Request that a Party owner or moderator has set to play as soon as possible after a given time.
_Avoid_: Timed request, booking

**Played Track**:
A history entry recording that a track played in a Party.
_Avoid_: Played song, history item

### Party roles

**Owner**:
A Party member with full control: Settings, role changes, Rules, Start Playback and everything a Moderator can do. The Party's creator is always an Owner.
_Avoid_: Host, manager, admin

**Moderator**:
A Party member who helps run the Party live without owning it. Can remove and schedule Requests, approve or reject Flagged Requests, view AI reviews and Played Tracks, and use the Dashboard's playback controls. Sees Rules and Members read-only. Cannot open Settings, change roles, edit Rules or start playback on the owner's Spotify. Only an Owner assigns the role.
_Avoid_: Mod (a Mod is a request-checking plug-in), admin

**VIP**:
A Party member exempt from the Party's request limit.
_Avoid_: Trusted user

**User**:
The default role: can search, request and vote.
_Avoid_: Guest, member (a member holds any role)

**Banned**:
A Party member who can no longer request or vote.
_Avoid_: Blocked, kicked

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

**Context Playlist**:
The empty playlist on the host's Spotify account that Music Party plays tracks in, so Spotify reports the party as the source. Music Party empties it every 15 minutes.
_Avoid_: party playlist, sentinel playlist

**Dashboard**:
The moderator page for running a Party live: playback controls, Start Playback, Up Next, Upcoming Songs and what needs attention. Open to the Party's Owners and Moderators, and to site admins.
_Avoid_: Control room, admin page
