<?php

namespace Database\Factories;

use App\Models\AdminHostSession;
use App\Models\Party;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminHostSession>
 */
class AdminHostSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'party_id' => Party::factory(),
        ];
    }
}
