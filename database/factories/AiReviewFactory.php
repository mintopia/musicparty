<?php

namespace Database\Factories;

use App\Enums\AiReviewOutcome;
use App\Models\AiReview;
use App\Models\Party;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiReview>
 */
class AiReviewFactory extends Factory
{
    public function outcome(AiReviewOutcome $outcome): static
    {
        return $this->state(['outcome' => $outcome]);
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory(),
            'user_id' => User::factory(),
            'track_provider' => 'fake',
            'track_id' => fake()->unique()->bothify('track-####'),
            'track_title' => fake()->words(3, true),
            'track_artists' => fake()->name(),
            'driver' => 'decision-model',
            'model' => 'jev-latest',
            'endpoint_host' => 'api.typesafe.ai',
            'request' => ['model' => 'jev-latest', 'state' => '{"title":"x"}', 'questions' => []],
            'response' => ['answers' => ['suitability' => ['score' => 4.0, 'confidence' => 0.9]]],
            'outcome' => AiReviewOutcome::Allow,
            'reason' => null,
            'score' => 4.0,
            'confidence' => 0.9,
            'latency_ms' => 420,
            'prompt_tokens' => 120,
            'completion_tokens' => 8,
            'total_tokens' => 128,
            'created_at' => now(),
        ];
    }
}
