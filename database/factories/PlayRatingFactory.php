<?php

namespace Database\Factories;

use App\Models\PartyMember;
use App\Models\PlayRating;
use App\Models\TrackRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlayRating>
 */
class PlayRatingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'track_request_id' => TrackRequest::factory(),
            'party_member_id' => PartyMember::factory(),
            'value' => 1,
        ];
    }

    public function dislike(): static
    {
        return $this->state(fn (): array => ['value' => -1]);
    }
}
