<?php

namespace Database\Factories;

use App\Models\PlayedSong;
use App\Models\SongRating;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SongRating>
 */
class SongRatingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'played_song_id' => PlayedSong::factory(),
            'user_id' => User::factory(),
            'value' => 1,
        ];
    }
}
