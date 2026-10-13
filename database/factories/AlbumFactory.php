<?php

namespace Database\Factories;

use App\Models\Album;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Album>
 */
class AlbumFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'provider_album_id' => fake()->unique()->regexify('[A-Za-z0-9]{22}'),
            'provider_id' => 'spotify',
            'image_url' => fake()->imageUrl(),
        ];
    }
}
