<?php

namespace Database\Factories;

use App\Enums\PartyModerationAction;
use App\Enums\PartyModerationType;
use App\Models\Party;
use App\Models\PartyModeration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartyModeration>
 */
class PartyModerationFactory extends Factory
{
    public function flagging(): static
    {
        return $this->state(['action' => PartyModerationAction::Flag]);
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory(),
            'type' => PartyModerationType::mtName,
            'value' => fake()->word(),
        ];
    }
}
