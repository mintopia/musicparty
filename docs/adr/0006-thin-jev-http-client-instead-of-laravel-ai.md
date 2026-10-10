# Thin Jev HTTP client instead of laravel/ai for the Jev driver

> **Superseded.** `laravel/ai` 1.2 ships a `Classification` API with `Score` questions and a `typesafe` (Jev) provider, so the premise below no longer holds. Both drivers now go through `laravel/ai` (`Classification::of()->question(new Score(...))`), with the normalized score from `ScoreAnswer::normalized()` driving the thresholds. No thin Jev HTTP client or `ReviewClassifier` interface exists.

The AI Request Review Mod needs two classifier drivers, OpenAI and Jev. The mods spec says both go through `laravel/ai` unless an ADR records otherwise. This is that ADR.

`laravel/ai` is a chat and structured-output SDK and does not support Jev as a provider. Jev is served by TypeSafe's Decisions API (a `state` plus typed Noul, Score and Choice questions), not an OpenAI-compatible chat endpoint. Jev's Score also returns a probability-weighted ordinal position, per-level probabilities and a confidence, which a `laravel/ai` score cannot represent. See `docs/research/jev-score-mapping.md`.

Decision:
- The OpenAI driver uses `laravel/ai`.
- The Jev driver is a thin HTTP client built on Laravel's `Http` facade (first-party), calling the Decisions API with a pinned model version.
- Both implement one `ReviewClassifier` interface that returns a normalized 0.0 to 1.0 score and an optional confidence. Thresholds for rejecting and holding apply to that normalized score, so Mod logic is driver-agnostic.

No new third-party dependency is added. Revisit if `laravel/ai` gains a Jev or Decisions provider.
