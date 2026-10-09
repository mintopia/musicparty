# Spike: Jev Score mapping on laravel/ai (T41, tasks 8.5)

## Question

Does `laravel/ai` Classification `Score` (0.0 to 1.0) map losslessly onto Jev's ordinal rubric levels, so that both AI Request Review drivers can go through `laravel/ai`?

## Findings

1. **`laravel/ai` cannot call Jev at all.** `laravel/ai` is a chat and structured-output SDK. The 12.x docs list the text providers as OpenAI, Anthropic, Gemini, Azure, Groq, xAI, DeepSeek, Mistral and Ollama. Jev is not among them. Custom base URLs exist only for OpenAI-compatible and similar endpoints. Jev is served by TypeSafe's Decisions API (also exposed on OpenRouter), and OpenRouter states that chat-completions SDKs do not work with it. The mapping question is therefore moot at the transport level.
2. **The documented `laravel/ai` surface has no dedicated Classification or Score type.** Scores and classes come from `HasStructuredOutput` agents, where the model fills an integer or enum field in a JSON schema. We did not find a `Classification` class in the docs. This was a documentation check, not a source check: `laravel/ai` is not yet in `composer.json`, so re-verify against the package when task 8.6 installs it.
3. **The two outputs are semantically different, even if the transport worked.**
   - Jev Score: a rubric of up to 10 ordered level descriptions. It returns a probability-weighted position (a float between levels, for example 1.43 on levels 0 to 2), a probability per level, and a confidence. It does not generate text and returns no rationale.
   - `laravel/ai`: an LLM-generated number or label, with no per-level probability or confidence. A model-reported 0.0 to 1.0 float is a self-reported guess, not a calibrated probability.
   - Mapping Jev to `laravel/ai` is lossy: the per-level distribution and the confidence are dropped. Mapping `laravel/ai` to Jev is not meaningful.
4. **The lossless common denominator is a normalized score.** Jev's position divided by (levels - 1) gives 0.0 to 1.0, which is what the spec's "threshold for rejecting and for holding" needs. This is the same normalization Promptfoo uses. The driver interface should return only that normalized score plus an optional confidence.
5. **Operational notes for the Jev client.** Pin a model version (`jev-1.13.x`) instead of `jev-latest`, because the alias moves and shifts scores against tuned thresholds. Levels are listed low to high, with a maximum of 10. Jev reads questions literally, so a rubric should be one criterion per question. Request and response field names were not verified against `docs.typesafe.ai/api`; the client task must confirm them.

## Decision

Lossy and not routable: implement a thin Jev HTTP client behind the same driver interface and keep the OpenAI driver on `laravel/ai`. See ADR-0006.

## Driver interface (for task 8.6)

```php
interface ReviewClassifier
{
    /** @param list<string> $rubricLevels ordered low (acceptable) to high (unacceptable) */
    public function classify(string $trackSummary, array $rubricLevels): ReviewScore;
}

final readonly class ReviewScore
{
    public function __construct(public float $normalized, public ?float $confidence = null) {}
}
```

`OpenAiReviewClassifier` uses a `laravel/ai` structured-output agent, tested with the SDK's agent fakes; `JevReviewClassifier` uses `Http::` against the Decisions API and is tested with `Http::fake()`.
