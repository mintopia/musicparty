<?php

namespace Database\Factories;

use App\Enums\SettingType;
use App\Models\LinkedAccount;
use App\Models\ProviderSetting;
use App\Models\SocialProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nickname' => fake()->userName(),
        ];
    }

    public function withSpotify(): static
    {
        return $this->afterCreating(function (User $user): void {
            $provider = SocialProvider::query()->firstOrCreate(
                ['code' => 'spotify'],
                SocialProvider::factory()->make(['code' => 'spotify'])->getAttributes()
            );
            foreach (['client_id', 'client_secret'] as $code) {
                if (! $provider->settings()->whereCode($code)->exists()) {
                    $setting = new ProviderSetting;
                    $setting->forceFill(['code' => $code, 'name' => $code, 'type' => SettingType::stString, 'value' => 'test']);
                    $setting->provider()->associate($provider);
                    $setting->save();
                }
            }
            LinkedAccount::factory()->create([
                'user_id' => $user->id,
                'social_provider_id' => $provider->id,
                'access_token' => 'token',
                'refresh_token' => 'refresh',
                'access_token_expires_at' => now()->addHour(),
            ]);
        });
    }
}
