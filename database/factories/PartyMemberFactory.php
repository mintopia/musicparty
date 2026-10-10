<?php

namespace Database\Factories;

use App\Domain\Party\PartyRole;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartyMember>
 */
class PartyMemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory(),
            'user_id' => User::factory(),
            'role' => PartyRole::Guest,
        ];
    }

    public function host(): static
    {
        return $this->state(fn (): array => ['role' => PartyRole::Host]);
    }

    public function moderator(): static
    {
        return $this->state(fn (): array => ['role' => PartyRole::Moderator]);
    }

    public function vip(): static
    {
        return $this->state(fn (): array => ['role' => PartyRole::Vip]);
    }

    public function banned(): static
    {
        return $this->state(fn (): array => ['banned' => true]);
    }
}
