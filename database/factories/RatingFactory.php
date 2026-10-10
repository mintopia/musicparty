<?php

namespace Database\Factories;

use App\Domain\Membership\Models\PartyMember;
use App\Domain\Queue\Models\Play;
use App\Domain\Queue\Models\Rating;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rating>
 */
class RatingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'play_id' => Play::factory(),
            'party_member_id' => PartyMember::factory(),
            'value' => 1,
        ];
    }

    public function dislike(): static
    {
        return $this->state(fn (): array => ['value' => -1]);
    }
}
