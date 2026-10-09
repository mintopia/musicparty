# Only instance admins issue API tokens

v3 exposes a full, OpenAPI-documented API, but ordinary users have no use for it, and long-lived tokens that users paste around are a liability. The API is called by only three kinds of client:
- the Vue UI, using the Sanctum session cookie
- Players, using Player Tokens that the Host issues per Party
- trusted integrations, using **Integration Tokens** that only instance admins issue.

We rejected self-service personal access tokens. We also rejected OAuth2 for third-party apps through Passport, which would add a consent flow and client registration that no one needs.
