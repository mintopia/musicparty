<?php

namespace Database\Factories;

use App\Domain\Party\Models\Party;
use App\Models\PartyMod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartyMod>
 */
class PartyModFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory(),
            'mod_id' => fake()->unique()->slug(2),
            'enabled' => true,
            'settings' => [],
        ];
    }

    public function enabled(): static
    {
        return $this->state(fn (): array => ['enabled' => true]);
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => ['enabled' => false]);
    }

    public function forMod(string $modId): static
    {
        return $this->state(fn (): array => ['mod_id' => $modId]);
    }
}
