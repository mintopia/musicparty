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
            'external_id' => fake()->unique()->numerify('##########'),
            'name' => fake()->userName(),
            'email' => fake()->safeEmail(),
        ];
    }

    public function verified(): static
    {
        return $this->state(['email_verified_at' => now()]);
    }
}
