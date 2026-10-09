<?php

namespace Database\Factories;

use App\Models\LinkedAccount;
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
            'external_id' => fake()->unique()->uuid(),
            'name' => fake()->name(),
            'access_token' => fake()->sha256(),
            'refresh_token' => fake()->sha256(),
            'access_token_expires_at' => now()->addHour(),
            'needs_relink' => false,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['access_token_expires_at' => now()->subMinute()]);
    }

    public function needingRelink(): static
    {
        return $this->state(fn (): array => ['needs_relink' => true]);
    }
}
