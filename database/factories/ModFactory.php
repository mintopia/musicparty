<?php

namespace Database\Factories;

use App\Models\Mod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mod>
 */
class ModFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(1),
            'name' => fake()->words(2, true),
        ];
    }
}
