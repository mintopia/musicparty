<?php

namespace Database\Factories;

use App\Models\Party;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use SpotifyWebAPI\SpotifyWebAPI;
use Tests\Support\FakeSpotify;

/**
 * @extends Factory<Party>
 */
class PartyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->regexify('[A-Z0-9]{6}'),
            'name' => fake()->words(2, true),
            'user_id' => User::factory()->withSpotify(),
            'active' => false,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (): void {
            if (! app()->bound(SpotifyWebAPI::class)) {
                FakeSpotify::install();
            }
        });
    }
}
