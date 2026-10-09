<?php

namespace Database\Factories;

use App\Domain\Queue\RequestStatus;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\TrackRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrackRequest>
 */
class TrackRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory(),
            'party_member_id' => PartyMember::factory(),
            'provider_track_id' => 'track-'.fake()->unique()->numberBetween(100, 99999),
            'title' => fake()->words(3, true),
            'artists' => [fake()->name()],
            'album' => fake()->words(2, true),
            'artwork_url' => null,
            'duration_ms' => fake()->numberBetween(120000, 300000),
            'explicit' => false,
            'status' => RequestStatus::Queued,
        ];
    }
}
