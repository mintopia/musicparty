<?php

namespace Database\Factories;

use App\Models\Party;
use App\Models\PartyLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartyLog>
 */
class PartyLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory(),
            'user_id' => User::factory(),
            'action' => 'party.updated',
            'acting_as_host' => false,
            'meta' => null,
        ];
    }
}
