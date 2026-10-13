<?php

namespace Database\Factories;

use App\Models\Artist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Artist>
 */
class ArtistFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'provider_artist_id' => fake()->unique()->regexify('[A-Za-z0-9]{22}'),
            'provider_id' => 'spotify',
        ];
    }
}
