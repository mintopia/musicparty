<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nickname' => fake()->userName(),
            'first_login' => false,
            'terms_agreed_at' => now(),
            'suspended' => false,
        ];
    }

    public function firstLogin(): static
    {
        return $this->state(fn (): array => ['first_login' => true, 'terms_agreed_at' => null]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => ['suspended' => true]);
    }
}
