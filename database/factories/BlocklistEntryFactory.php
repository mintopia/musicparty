<?php

namespace Database\Factories;

use App\Domain\Party\Models\BlocklistEntry;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\BlocklistMatchType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlocklistEntry>
 */
class BlocklistEntryFactory extends Factory
{
    protected $model = BlocklistEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory(),
            'match_type' => BlocklistMatchType::TrackName,
            'value' => fake()->unique()->words(3, true),
            'is_regex' => false,
            'is_enabled' => true,
            'notes' => null,
        ];
    }

    public function enabled(): static
    {
        return $this->state(fn (): array => ['is_enabled' => true]);
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => ['is_enabled' => false]);
    }

    public function regex(): static
    {
        return $this->state(fn (): array => ['is_regex' => true]);
    }

    public function matching(BlocklistMatchType $type, string $value): static
    {
        return $this->state(fn (): array => ['match_type' => $type, 'value' => $value]);
    }
}
