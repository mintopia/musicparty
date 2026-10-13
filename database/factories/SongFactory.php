<?php

namespace Database\Factories;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Song;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Song>
 */
class SongFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider_track_id' => fake()->unique()->regexify('[A-Za-z0-9]{22}'),
            'provider_id' => 'spotify',
            'name' => fake()->words(3, true),
            'length' => fake()->numberBetween(120000, 300000),
            'album_id' => Album::factory(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Song $song): void {
            if ($song->artists()->doesntExist()) {
                $song->artists()->attach(Artist::factory()->create());
            }
        });
    }
}
