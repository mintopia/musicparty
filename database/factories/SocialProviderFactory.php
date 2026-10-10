<?php

namespace Database\Factories;

use App\Domain\Identity\Models\SocialProvider;
use App\Domain\Identity\SocialProviders\DiscordProvider;
use App\Domain\Identity\SocialProviders\FacebookProvider;
use App\Domain\Identity\SocialProviders\GoogleProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialProvider>
 */
class SocialProviderFactory extends Factory
{
    protected $model = SocialProvider::class;

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

    public function google(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Google',
            'code' => 'google',
            'provider_class' => GoogleProvider::class,
        ]);
    }

    public function facebook(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Facebook',
            'code' => 'facebook',
            'provider_class' => FacebookProvider::class,
        ]);
    }
}
