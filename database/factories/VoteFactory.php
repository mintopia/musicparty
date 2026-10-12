<?php

namespace Database\Factories;

use App\Models\UpcomingSong;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vote>
 */
class VoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'upcoming_song_id' => UpcomingSong::factory(),
            'user_id' => User::factory(),
            'value' => 1,
        ];
    }

    public function up(): static
    {
        return $this->state(['value' => 1]);
    }

    public function down(): static
    {
        return $this->state(['value' => -1]);
    }
}
