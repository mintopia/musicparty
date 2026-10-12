<?php

namespace Database\Factories;

use App\Domain\Mod\Whamageddon\Models\WhamageddonState;
use App\Domain\Party\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhamageddonState>
 */
class WhamageddonStateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory(),
            'request_id' => null,
            'accumulated_bonus' => 0,
            'last_played_at' => null,
        ];
    }
}
