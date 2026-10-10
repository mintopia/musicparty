<?php

namespace Database\Factories;

use App\Domain\Identity\Models\User;
use App\Domain\Party\Models\Party;
use App\Domain\Party\Models\PartyLogEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartyLogEntry>
 */
class PartyLogEntryFactory extends Factory
{
    protected $model = PartyLogEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory(),
            'user_id' => User::factory(),
            'action' => 'party.settings_changed',
            'subject' => 'name',
            'details' => ['old' => 'Old', 'new' => 'New'],
        ];
    }

    public function bySystem(string $component = 'player'): static
    {
        return $this->state(fn (): array => ['user_id' => null, 'system_actor' => $component]);
    }
}
