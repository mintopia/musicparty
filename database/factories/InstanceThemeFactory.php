<?php

namespace Database\Factories;

use App\Models\InstanceTheme;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstanceTheme>
 */
class InstanceThemeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tokens' => [
                'light' => ['primary' => '#7c3aed'],
                'dark' => ['primary' => '#a78bfa'],
                'font' => 'inter',
            ],
        ];
    }
}
