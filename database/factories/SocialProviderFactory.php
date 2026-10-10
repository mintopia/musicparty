<?php

namespace Database\Factories;

use App\Models\SocialProvider;
use App\Services\SocialProviders\DiscordProvider;
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
            'name' => 'Discord',
            'code' => 'discord',
            'provider_class' => DiscordProvider::class,
            'supports_auth' => true,
            'enabled' => true,
            'auth_enabled' => true,
            'can_be_renamed' => false,
        ];
    }
}
