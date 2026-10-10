<?php

namespace Database\Factories;

use App\Domain\Stats\Actions\ComputePartyStats;
use App\Models\Party;
use App\Models\PartyStat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartyStat>
 */
class PartyStatFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory(),
            'payload' => ComputePartyStats::empty(),
        ];
    }
}
