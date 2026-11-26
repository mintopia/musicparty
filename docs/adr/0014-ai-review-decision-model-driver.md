# AI Request Review gains a decision-model driver over plain HTTP

Jev-style TypeSafe models are not chat models: they take `{model, state, questions}` and return scored answers with a confidence, via OpenRouter's Decisions API (`https://openrouter.ai/api/alpha/decisions`) or direct TypeSafe. laravel/ai has no driver for that transport, so the AI Request Review mod gets a third driver, `decision-model`, built on Laravel's HTTP client.

**One `score` question.** The party guidelines go in the question instructions, the track details in `state` (never any user data). Five ordered criteria run from "clearly against the guidelines" through "unsure, a human should decide" to "clearly fine". Scores are ordinal, so only bands are used: below 0.75 rejects, below 2.5 flags, anything higher allows. The reason shown is the chosen level's legend text.

**Confidence is a separate axis.** Below the party's confidence threshold (default 0.5), or with no confidence returned, the request is flagged for a manager whatever the score. Flag still degrades to allow when the party has flagging off, as for the other drivers.

**Fail open.** Timeouts, non-2xx responses, missing keys and malformed answers allow the request, matching the other drivers. Log lines carry only a fixed message or exception class, never the response body or the key. Redirects are not followed so the bearer token cannot be forwarded.

**Settings and secrets.** `decision_endpoint` (validated `url:https`, default the OpenRouter URL), `decision_model` (default `typesafe/jev-1.13`) and `confidence_threshold` are new settings; the existing `api_key` secret is shared by all drivers. `stSecret` already encrypts at rest and is never returned to the frontend, so no new setting types were needed. The endpoint is manager-supplied, so the reviewer also refuses localhost, `.internal`/`.local` names and private or reserved IP literals before sending the key.

**Known limit.** Hostnames that resolve to internal addresses are not blocked, only literals and obvious names.

## Amendment: the TypeSafe chat driver is removed

The original `typesafe` driver (laravel/ai `Classification` against `api.typesafe.ai/v1/systemone`) is gone. `decision-model` talks to the same `{model, state, questions}` API, either through OpenRouter or direct TypeSafe, so it replaces `typesafe` rather than sitting beside it. The driver choices are now `decision-model` (the default for new parties) and `openai-compatible`.

A data migration moves every Party that used `typesafe`, explicitly or by default with the mod enabled, to `decision-model` with `decision_endpoint = https://api.typesafe.ai/v1/systemone` and `decision_model` set to its previous `model` setting, or `jev-latest` if it had none. The `api_key` is kept.
