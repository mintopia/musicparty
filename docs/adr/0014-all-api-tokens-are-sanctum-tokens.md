# All API tokens are Sanctum tokens

v3 has two token mechanisms. Player Tokens are Sanctum personal access tokens whose tokenable is the `Party`, and the Player channel is keyed by party code (`player.{code}`). Integration Tokens have their own table, their own hashing and a custom `IntegrationTokenGuard`, which writes `last_used_at` on every request. Neither choice was recorded, and the 2026-10-10 v3 review flagged the custom guard as a second security-critical code path that Sanctum already covers.

Decision:
- Integration Tokens become Sanctum tokens. Their tokenable is a new `Integration` model (name, issuing admin, created and revoked times) in the Admin context. Abilities are Sanctum abilities, checked with `tokenCan`. `IntegrationTokenGuard`, its config entry and the `integration_tokens` table are removed.
- Player Tokens stay Sanctum tokens on the `Party` tokenable. One Party may have several Player Tokens, and the Player channel stays keyed by party code because a Party has one Player at a time.
- Both kinds of token expire. The lifetime is configured per kind (Player Tokens default to 30 days, Integration Tokens to none unless the admin sets one), and the daily `sanctum:prune-expired` removes them.
- `last_used_at` is written at most once a minute per token.
- Only admins issue Integration Tokens and only Hosts issue Player Tokens (ADR-0005 still applies).

Consequences: one guard, one token table, one prune job. Existing Integration Tokens cannot be converted because the hashes differ, so the upgrade drops them and admins issue new ones. API documentation describes both kinds as Sanctum bearer tokens with abilities.
