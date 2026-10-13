<?php

namespace Database\Factories;

use App\Models\Theme;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Theme>
 */
class ThemeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'readonly' => false,
            'active' => false,
            'dark_mode' => false,
            'tokens' => ['primary' => '#0054a6', 'nav-background' => '#182433'],
            'css' => '',
        ];
    }

    public function active(): static
    {
        return $this->state(['active' => true]);
    }
}
