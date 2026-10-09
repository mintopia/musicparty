<?php

namespace Database\Factories;

use App\Models\PartyMember;
use App\Models\RequestVote;
use App\Models\TrackRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestVote>
 */
class RequestVoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'track_request_id' => TrackRequest::factory(),
            'party_member_id' => PartyMember::factory(),
            'value' => 1,
        ];
    }

    public function down(): static
    {
        return $this->state(fn (): array => ['value' => -1]);
    }
}
