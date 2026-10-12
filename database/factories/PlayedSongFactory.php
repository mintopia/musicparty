<?php

namespace Database\Factories;

use App\Models\Party;
use App\Models\PlayedSong;
use App\Models\Song;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlayedSong>
 */
class PlayedSongFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory(),
            'song_id' => Song::factory(),
            'played_at' => now(),
        ];
    }
}
