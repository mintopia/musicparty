# E1 Security hotfix

Close the exploitable holes in the current app quickly, without changing anything else.

- **Status:** Draft
- **Wave:** 1
- **blocked_by:** none (ships before anything else)

## Problem

The `develop` baseline has several directly exploitable issues:

1. **Stray cookie file.** `cookies.json` is tracked at the repo root (added in d53ebe4) even though `.gitignore:28` lists it. It is an example Spotify web-player cookie set (`sp_dc`, `sp_key`, `sp_t`), a leftover from an old way of getting Spotify websocket data.
2. **Open token relay.** `POST /proxy` (`routes/web.php:40`, `HomeController::proxy` at `HomeController.php:19`) is unauthenticated and CSRF-exempt (`VerifyCsrfToken.php:16`). It curls `open.spotify.com/get_access_token` with caller-supplied cookies, logs the full response (access tokens in logs) and returns the token. Nothing in the app calls it. `VerifyCsrfToken.php:15` also exempts `/webhooks/tickets/*`, a route that no longer exists.
3. **Spotify access token on a public channel.** `SpotifyAccessTokenUpdatedEvent` broadcasts on `new Channel("spotifytoken.{id}")` (`app/Events/User/SpotifyAccessTokenUpdatedEvent.php:39`). Anyone with the Reverb app key (shipped to every browser) can subscribe to `spotifytoken.<small int>` and receive a Host's live token with playback and playlist scopes. The auth callback at `routes/channels.php:26` is bypassed because the channel is public. The Web Player listens at `resources/views/parties/player.blade.php:34`. The Web Player page is guarded by `can:update,party`, so every manager sees the owning user's token rendered into its HTML.
4. **Unauthorised YouTube broadcast.** `POST parties/{party}/youtube` (`routes/web.php:44`) sits in the "always available" group; `PartyPlayYouTubeRequest::authorize()` returns true. Any visitor with a session CSRF token can push a video to any Party's TV screen. `PartyController.php:103-104` dereferences `parse_url(...)['host']` unchecked (500 on host-less input). `PartyController::youtube` (`:92-96`) passes `$request->user()` (null for guests) to `Party::canBeManagedBy(User $user)` (`Party.php:682`), a TypeError for guests.
5. **Unthrottled Playback Webhook.** `POST /webhooks/parties/{party}/soloist` (`routes/webhooks.php:11`) has no auth and no throttle; each call dispatches `PartyUpdate` (Spotify API calls). Party codes are 4 letters and enumerable.
6. **Account takeover via email auto-merge.** `AbstractSocialProvider::user` (`:130-138`) logs the visitor in as whichever user owns any `LinkedAccount` with the same email, with no verification check on either side.
7. **Role promotion.** `PartyMemberController::update` (`:133`) looks up the role by code with no restriction: a manager can assign `owner`, and an unknown code associates `null`.
8. **Broken LinkedAccount removal.** `routes/web.php:78-79` map `linkedaccounts.delete` (GET) and `linkedaccounts.destroy` (DELETE) to `LinkedAccountController::store`, so removal never works and a GET to `.../delete` runs the link flow.
9. **Blocklist regex.** `PartyModeration.php:122` runs a user-supplied regex via `@preg_match` (ReDoS), and the wildcard path (`:129`) builds a pattern without `preg_quote` (regex injection).

## Goals

- Remove the leaked file and the dead relay.
- No Spotify access token reaches anyone but its owner's authenticated session, or users allowed to open the Web Player.
- State-changing routes require the right identity.
- Email auto-merge only happens on provider-verified emails.
- Each fix is covered by a Pest test that fails before and passes after.

## Non-goals

- Playback Webhook token auth (deferred; a later epic, out of this rework's scope beyond the rate limit here).
- The wider test harness, static analysis and CI (E2).
- Reliability races and known non-security bugs (E5).
- Git history rewriting. `cookies.json` was only an example, so history is left alone and nothing is force-pushed.
- Party join codes or private Parties.

## Requirements

1. `cookies.json` is deleted from the tree; `.gitignore` keeps excluding it. History is not rewritten.
2. The `proxy` route, `HomeController::proxy` and both stale CSRF exemptions (`/proxy`, `/webhooks/tickets/*`) are removed. `POST /proxy` returns 404 (or 405).
3. `SpotifyAccessTokenUpdatedEvent` broadcasts on `PrivateChannel("spotifytoken.{id}")`. The `spotifytoken.{userId}` channel authorises only the user whose id matches. The Web Player subscribes with `Echo.private(...)` and keeps receiving refreshed tokens.
4. The Web Player page is available only to members with the `owner` role on the Party and to instance admins. Until E8 reworks token delivery, only the owning user receives refreshed tokens.
5. `POST parties/{party}/youtube` requires an authenticated user who passes the Party `update` ability (the same people who see the control form via `canManage`). Others get 401/403 and nothing is broadcast.
6. The video input is validated: it must parse to a URL with a host on a YouTube allow-list (`youtube.com`, `www.youtube.com`, `youtu.be`, `m.youtube.com`) and yield a video id; otherwise a validation error, never a 500.
7. The TV, YouTube and YouTube player GET pages render for guests (`canManage` is false for a null user) without a TypeError.
8. The Soloist Playback Webhook is limited per Party by a leaky bucket that allows bursts. The burst size and leak rate are configurable in `config/musicparty.php`, with defaults that never throttle a well-behaved Encore. Over the limit it returns 429 and dispatches no `PartyUpdate`.
9. Each social provider declares whether the email it returned is verified:
   - Discord: verified when the raw user's `verified` is true.
   - Twitch: verified (Twitch only issues a verified email).
   - Steam: no email, never verified.
   - Spotify, SpotifySearch, Laravel Passport: never verified.
10. `linked_accounts` gains a nullable `email_verified_at` timestamp, set when the provider asserts the email is verified and cleared when it does not, on every login or link through that account. The migration backfills existing Twitch rows as verified. Discord rows get it at their next login.
11. Login auto-merges into an existing user only when the incoming email is verified AND it matches a `LinkedAccount` whose `email_verified_at` is set. An unverified email never causes a merge.
12. Linking a new account while logged in still works for every provider, verified or not, except that a link is still refused when its email belongs to another user's `LinkedAccount`, whether verified or not (today's "already associated" behaviour).
13. Merging two accounts with verified emails keeps working (e.g. Discord then Twitch with the same verified address logs into one user).
14. `PartyMemberUpdateRequest` validates `role` against existing `PartyMemberRole` codes. Assigning `owner` is refused (403) unless the actor is the Party's owning user (`parties.user_id`) or an instance admin. Nobody else can demote an owner. A null role can never be associated.
15. `linkedaccounts.delete` shows a confirmation and `linkedaccounts.destroy` removes the account, guarded by the existing `can:update,linkedaccount` middleware, mirroring the admin `LinkedAccountController` delete/destroy flow.
16. Wildcard Blocklist rules escape every character except `*` with `preg_quote` before building the pattern.
17. Regex Blocklist rules are validated on save (the pattern compiles; length capped) and evaluated with a bounded backtrack limit. A pattern that errors or hits the limit counts as no match and is logged, never a 500.
18. Unused `use` imports of the non-existent `TicketController` and `WebhookController` in `routes/web.php` are removed.
19. Tests run against in-memory SQLite only, configured in `phpunit.xml`, so no test can touch the dev database.

## Acceptance criteria

- `git ls-files cookies.json` is empty.
- Pest feature tests prove:
  - `/proxy` is gone
  - the token event's channel is private and other users are denied channel auth
  - a manager without the `owner` role cannot open the Web Player; an owner-role member and an admin can
  - guests and non-managers cannot post a YouTube video, and malformed URLs return 422; guests can load the TV and YouTube pages
  - the Soloist webhook accepts a burst up to the configured size, returns 429 once the bucket is full, accepts again after it leaks, and another Party is unaffected
  - each provider's verified/unverified email behaves per requirements 9-13, existing Twitch rows are backfilled as verified, and a link whose email belongs to another user is refused
  - `owner` and unknown roles are refused, and a manager cannot demote an owner
  - LinkedAccount delete/destroy remove the account
  - wildcard and catastrophic regex rules do not error or hang
- The Web Player still plays, and still picks up a refreshed token (manual check on the local deployment).
- Discord login with a verified email that matches an existing verified Discord or Twitch account logs into that user (manual check).

## Functionality that must be retained

- Email account merge for verified emails; manual linking of any provider.
- YouTube and TV pages for their current audiences; managers can still broadcast videos.
- librespot, simple and soloist Playback Webhooks, including Encore's unauthenticated soloist calls under the rate limit.
- Wildcard and regex Blocklist rules with their current matching semantics for legitimate patterns.
- Party owners' role changes, and VIP/user/banned role changes by managers.

## Suggested ticket slices

| # | Slice | blocked_by |
|---|---|---|
| 1 | Test DB guard: in-memory SQLite in `phpunit.xml`, convert the two stub tests to Pest (req 19) | none |
| 2 | Remove `cookies.json`, `/proxy`, `HomeController::proxy`, stale CSRF exemptions and dead imports (req 1, 2, 18) | 1 |
| 3 | Private `spotifytoken` channel, Web Player subscription and owner-role access (req 3, 4) | 1 |
| 4 | YouTube route authorisation, URL validation, guest TypeError (req 5-7) | 1 |
| 5 | Per-Party leaky-bucket limit on the Soloist webhook (req 8) | 1 |
| 6 | Verified-email flag per provider, `email_verified_at` with Twitch backfill, merge only on verified both sides, link refusal (req 9-13) | 1 |
| 7 | Role assignment validation (req 14) | 1 |
| 8 | LinkedAccount delete/destroy routes (req 15) | 1 |
| 9 | Blocklist wildcard escaping and bounded regex (req 16, 17) | 1 |

Slices 2-9 are independent of each other.

## Risks and open questions

Implementation-level; settle when tickets are written.

- **Leaky-bucket implementation.** Laravel's `RateLimiter` is fixed-window, so the bucket needs a small cache-backed implementation (atomic on Redis). Default burst and leak rate are chosen from Encore's `webhooks.delay_ms`.
- **Regex limits.** Length cap and backtrack limit values.
