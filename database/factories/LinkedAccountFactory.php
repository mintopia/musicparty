<?php

namespace Database\Factories;

use App\Models\LinkedAccount;
use App\Models\SocialProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LinkedAccount>
 */
class LinkedAccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'social_provider_id' => SocialProvider::factory(),
            'external_id' => (string) fake()->unique()->numberBetween(1000, 999999),
            'name' => fake()->userName(),
        ];
    }
}
