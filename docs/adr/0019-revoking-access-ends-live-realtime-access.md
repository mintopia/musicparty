# Revoking access ends live realtime access

Reverb authorises a private or presence channel once, when the client subscribes. After that nothing in the app checks again. A revoked Soloist keeps sending frames, which `HandlePlayerClientEvent` keeps processing, and keeps receiving `player.command` until it reconnects. A Banned Member keeps their member channel, and a demoted Moderator keeps receiving Pending Requests and Party Log entries on the Moderator channel. The 2026-10-10 v3 review raised this as a security gap. We considered recording it as an accepted gap and rejected that.

Decision:
- Members: every authenticated Party page joins the Party's presence channel, which tags the connection with the user's id inside Reverb. When a Member is Banned, loses a Party Role, or is suspended by an admin, the app calls Reverb's terminate-user-connections endpoint for that user after the change commits. The client reconnects, and every channel is authorised again against current state.
- Players: when a Player channel is authorised, the app records which Player Token owns that socket. `HandlePlayerClientEvent` checks the token is still valid before accepting a frame, and disconnects the socket if it is not. Revoking a token also stops commands from being published for it. A revoked Player is cut off on its next frame; Soloist sends position frames every few seconds.
- Channel authorisation stays deny-by-default and is re-evaluated on every subscription.
- The TV screen and the public `party.{code}` channel stay unauthenticated by design. They show only public state (no Member ids, no Pending Requests). The code-addressed web routes are throttled per IP to slow enumeration of the 4-letter code space.

Consequences: revocation takes effect within seconds instead of at the next reconnect. A terminated Member's other tabs reconnect too. Reverb's HTTP API must be reachable from the app on the internal network, which it already is (ADR-0008).
