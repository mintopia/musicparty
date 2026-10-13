<?php

namespace Database\Factories;

use App\Models\Party;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UpcomingSong>
 */
class UpcomingSongFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory(),
            'song_id' => Song::factory(),
            'user_id' => User::factory(),
        ];
    }

    public function waiting(): static
    {
        return $this->state(['queued_at' => null]);
    }

    public function sentToPlayer(): static
    {
        return $this->afterMaking(function (UpcomingSong $request) {
            $request->queued_at ??= now();
            $request->sent_track_id ??= $request->song->provider_track_id;
        });
    }
}
