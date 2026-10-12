<?php

namespace Database\Factories;

use App\Domain\Membership\Models\PartyMember;
use App\Domain\Mod\Trust\Models\TrustScore;
use App\Domain\Party\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrustScore>
 */
class TrustScoreFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory(),
            'party_member_id' => fn (array $attributes) => PartyMember::factory()->state(['party_id' => $attributes['party_id']]),
            'score' => 0.5,
            'calculated_at' => now(),
        ];
    }
}
