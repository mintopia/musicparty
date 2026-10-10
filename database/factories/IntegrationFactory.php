<?php

namespace Database\Factories;

use App\Domain\Admin\Models\Integration;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Integration>
 */
class IntegrationFactory extends Factory
{
    protected $model = Integration::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'issued_by' => User::factory(),
            'revoked_at' => null,
        ];
    }
}
