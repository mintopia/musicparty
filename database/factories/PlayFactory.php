<?php

namespace Database\Factories;

use App\Domain\Party\Models\Party;
use App\Models\Play;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Play>
 */
class PlayFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory(),
            'track_request_id' => null,
            'party_member_id' => null,
            'provider_track_id' => 'track-'.fake()->unique()->numberBetween(100, 99999),
            'title' => fake()->words(3, true),
            'artists' => [fake()->name()],
            'album' => fake()->words(2, true),
            'artwork_url' => null,
            'duration_ms' => fake()->numberBetween(120000, 300000),
            'explicit' => false,
            'selection_mode' => 'deterministic',
            'selection_score' => 0,
            'played_at' => now(),
        ];
    }
}
