<?php

namespace Database\Factories;

use App\Models\Party;
use App\Models\PartySearch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartySearch>
 */
class PartySearchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $query = fake()->unique()->lexify('??????');

        return [
            'party_id' => Party::factory(),
            'user_id' => null,
            'query' => $query,
            'term' => mb_strtolower($query),
        ];
    }
}
