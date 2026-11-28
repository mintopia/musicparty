# AI Request Review gains a decision-model driver over plain HTTP

Jev-style TypeSafe models are not chat models: they take `{model, state, questions}` and return scored answers with a confidence, via OpenRouter's Decisions API (`https://openrouter.ai/api/alpha/decisions`) or direct TypeSafe. laravel/ai has no driver for that transport, so the AI Request Review mod gets a third driver, `decision-model`, built on Laravel's HTTP client.

**One `score` question.** The party guidelines go in the question instructions, the track details in `state` (never any user data). Five ordered criteria run from "clearly against the guidelines" through "unsure, a human should decide" to "clearly fine". Scores are ordinal, so only bands are used: below 0.75 rejects, below 2.5 flags, anything higher allows. The reason shown is the chosen level's legend text.

**Confidence is a separate axis.** Below the party's confidence percentage (default 50), or with no confidence returned, the request is allowed whatever the score (band `low_confidence` in the log); at or above it the score bands apply. Flag still degrades to allow when the party has flagging off, as for the other drivers.

**Fail open.** Timeouts, non-2xx responses, missing keys and malformed answers allow the request, matching the other drivers. Log lines carry only a fixed message or exception class, never the response body or the key. Redirects are not followed so the bearer token cannot be forwarded.

**Settings and secrets.** `decision_endpoint` (validated `url`, default the OpenRouter URL), `decision_model` (default `typesafe/jev-1.13`) and `confidence_percentage` (an integer 0-100) are new settings; the existing `api_key` secret is shared by all drivers. `stSecret` already encrypts at rest and is never returned to the frontend, so no new setting types were needed. The endpoint is manager-supplied, so the reviewer refuses it before sending the key unless a site admin has allowed internal endpoints (see the amendment below).

## Amendment: the TypeSafe chat driver is removed

The original `typesafe` driver (laravel/ai `Classification` against `api.typesafe.ai/v1/systemone`) is gone. `decision-model` talks to the same `{model, state, questions}` API, either through OpenRouter or direct TypeSafe, so it replaces `typesafe` rather than sitting beside it. The driver choices are now `decision-model` (the default for new parties) and `openai-compatible`.

A data migration moves every Party that used `typesafe`, explicitly or by default with the mod enabled, to `decision-model` with `decision_endpoint = https://api.typesafe.ai/v1/systemone` and `decision_model` set to its previous `model` setting, or `jev-latest` if it had none. The `api_key` is kept.

## Amendment: a `criterion` choice question says what a request fails

The payload carries a second question, `criterion`, of type `choice`, next to `suitability`. Its keys are `AiReviewCriterion`: `none`, `guidelines`, `explicit`, `vibe`, `troll`, `other`. It has the same untrusted-data warning and party guidelines as the score question. Questions are answered independently, so the criterion cannot be informed by the score.

The criterion never changes the decision: allow, flag and reject still come only from the score bands and confidence threshold. When the outcome is flag or reject and a criterion other than `none` was chosen, its label prefixes the reason ("Vibe: ..."). The chosen key and its probability are stored on `ai_reviews` (`criterion`, `criterion_confidence`) and logged on `ai-review.decision`.

A refusal, a missing answer, a non-`choice` answer or an unknown key leaves the criterion null and the review proceeds as before, so fail-open still holds.

## Amendment: endpoint safety, the override and a circuit breaker

**DNS check.** Before any request or key is sent, for both the decision-model and OpenAI-compatible drivers, the endpoint's hostname is resolved and the review is refused if any address is private, loopback, link-local (including 169.254.169.254) or reserved, or if the name is localhost, `.internal` or `.local`. A refused endpoint sends no key and fails open. The decision-model request pins the vetted address with curl's resolve option so a second lookup cannot rebind to an internal one.

**Site-admin override.** `allow_internal_endpoints` is an instance-only mod setting, so only site admins can set it and a Party Owner cannot. When on, the check is skipped so self-hosted models on internal addresses still work.

**HTTPS rule dropped.** `decision_endpoint` is validated as `url` rather than `url:https`; both http and https are accepted for both drivers.

**Timeout cap.** The review timeout is capped at 20 seconds, in the setting's validation and again at runtime for values stored earlier.

**Circuit breaker.** After 3 consecutive failures for a Party, the AI stage is skipped for 60 seconds and the Request is allowed and logged (`circuit_open`), consistent with fail-open. A success resets the count.

**Verdict cache.** Successful verdicts are cached per Party and track for 5 minutes, so retrying a rejected track does not bill the model again. Failures are never cached.
