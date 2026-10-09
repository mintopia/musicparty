# Music Party

A collaborative party jukebox: guests at a party search for tracks, request them and vote on the shared queue, and the party's music plays from it.

## Language

### Parties and membership

**Party**:
A single event's shared jukebox, bound to exactly one Music Provider and played through one Player.

**Live**:
A Party that is playing and accepting Requests.

**Paused**:
A Party that remains visible but whose Player is idle.

**Ended**:
A Party closed to playback, kept as read-only history; its Host may reopen it.

**Mod**:
An optional, per-Party extension that can judge Requests, influence selection, act on a schedule and change how things are shown.
_Avoid_: Plugin, addon

**Decoration**:
Structured presentation data a Mod attaches to a Request or Play, such as a badge, label, icon or accent.
_Avoid_: CSS classes, styling

**Member**:
A user who has joined a Party, holding exactly one Party Role.
_Avoid_: Participant, attendee

**Host**:
The Member who owns a Party and controls its settings and Player.
_Avoid_: Owner

**Moderator**:
A Member trusted to manage the Queue and Ban Members, without access to Party settings or the Player.

**VIP**:
A Member exempt from per-member request limits.

**Guest**:
An ordinary Member who can request Tracks and vote.
_Avoid_: User (in a Party context)

**Ban**:
A Member's barred status in a Party, preventing them from requesting, voting or rating; it is a status, not a role.

**Party Log**:
A Party's audit trail of moderation, settings, Player and Mod actions, visible to its Host and Moderators.
_Avoid_: Audit log, history

**Blocklist**:
A Party's rules for rejecting Tracks by name, artist, album or identifier.
_Avoid_: Moderation rules

### Music and the queue

**Track**:
An item in a Music Provider's catalogue.
_Avoid_: Song

**Request**:
A Track a Member asked to be played, waiting in a Party's Queue.
_Avoid_: Upcoming song, queue item

**Queue**:
A Party's queued Requests, ordered for selection by score.

**Pending**:
A Request held for a Host or Moderator to approve before it enters the Queue.
_Avoid_: Awaiting moderation, held

**Up Next**:
The single Request locked in as the next to play; Votes no longer change it.
_Avoid_: Next song, queued

**Vote**:
A Member's upvote or downvote on a Request.
_Avoid_: Upvote (as the general term)

**Fallback Playlist**:
The Provider playlist used to top up a Party's Queue when it runs low; Requests made from it have no requester.
_Avoid_: Backup playlist

**History Playlist**:
The Provider playlist that a Party's played Tracks are appended to.

**Play**:
The record of a Track having been played at a Party.
_Avoid_: Played song

**Rating**:
A Member's like or dislike of a Play.

### Providers and players

**Music Provider**:
A music service that supplies the catalogue, search and track metadata for a Party (e.g. Spotify, later Tidal).
_Avoid_: Backend, service, source

**Player**:
The means by which Music Party observes and controls what is actually playing for a Party.
_Avoid_: Device, driver, playback backend

**Compatibility**:
The declared set of Music Providers a given kind of Player can play; a Party may only pair a Provider with a compatible Player.

**Polling Player**:
A Player that observes playback by periodically asking the Music Provider what is playing on the Host's account.

**Soloist Player**:
A Player backed by a Soloist proxy that connects in to Music Party and pushes playback events.

**Browser Player**:
A Player that plays audio in the Host's web browser.

**Feed Mode**:
How a Player receives the Up Next Request: just-in-time near the end of the current Track, or one Track ahead.

**Webhook**:
An inbound authenticated HTTP call from an external integration telling a Polling Player to check playback immediately; it cannot control playback.
_Avoid_: Nudge, ping

**Player Token**:
A credential Music Party issues to a single Player connection for a single Party, revocable by the Host.

**Integration Token**:
An API credential that only instance admins can issue, for a trusted external integration; ordinary users never hold one.
_Avoid_: Personal access token, API key

### Stats

**Live Stats**:
A Party's running statistics, visible to its Members while the Party is in progress.

**Party Export**:
The versioned dataset of an Ended Party, intended for external Wrapped-style reporting.
_Avoid_: Dump, report

### Appearance

**Instance Theme**:
The site-wide default set of design tokens for the whole Music Party installation.
_Avoid_: Global theme, skin

**Party Theme**:
A Party's overrides of a subset of the Instance Theme's design tokens.

**Colour Scheme**:
A user's light, dark or system preference, applied on top of whichever theme is in effect.
_Avoid_: Dark mode, theme
