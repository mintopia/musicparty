# A new social login with the same email as an existing user joins that user's account

v2 attached a new provider login to the user whose linked account had the same email (`AbstractSocialProvider.php:131-141`). v3 matches only on (provider, external id) in `app/Domain/Identity/Actions/ResolveSocialUser.php`, so a guest who signs in with a second provider gets a second account (triage dec-4). The earlier recommendation was not to reinstate the merge, because an unverified email lets someone take over another person's account. The project owner ruled to reinstate it, on the basis that providers generally verify email.

We considered no merge, which the owner rejected, and merging only for an allow-list of providers, which is more to configure and still leaves the same trust question per provider.

Decision:
- This applies on login only. Linking from the Profile page keeps working by explicit consent.
- When no linked account matches (provider, external id) and the provider returns a non-empty email, compare it, trimmed and case-insensitively, with the email of every existing linked account.
- If exactly one user matches, attach the new linked account to that user and log in as them.
- If the provider explicitly reports the email as unverified (for example Google `email_verified=false`, Discord `verified=false`), do not merge.
- If more than one distinct user matches, do not merge. Create a new user as today and log a warning.
- If the match is a suspended user, refuse the login as suspended.
- Each automatic merge writes an admin audit entry and shows the user an info message naming the provider.
- `linked_accounts` gets an indexed, normalised email lookup column, because the current `email` column is `longText` and cannot be indexed.

Consequences: a provider that reports unverified email as verified, or a provider an attacker controls, can be used to take over an account. We accept that risk on the owner's ruling. Whether admin accounts are excluded from merging is an open question in the `v2-parity-restore` design.md; the recommendation, following the ruling, is no exclusion.
