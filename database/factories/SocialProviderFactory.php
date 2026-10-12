<?php

namespace Database\Factories;

use App\Models\SocialProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialProvider>
 */
class SocialProviderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'code' => fake()->unique()->slug(1),
            'provider_class' => 'App\\SocialProviders\\Discord',
            'supports_auth' => true,
            'enabled' => true,
            'auth_enabled' => true,
            'can_be_renamed' => false,
        ];
    }
}
