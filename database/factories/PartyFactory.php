<?php

namespace Database\Factories;

use App\Domain\Party\PartyState;
use App\Models\Party;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Party>
 */
class PartyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => $this->uniqueCode(),
            'name' => fake()->words(2, true),
            'user_id' => User::factory(),
            'music_provider' => 'fake',
            'player_kind' => 'fake',
            'state' => PartyState::Paused,
        ];
    }

    public function live(): static
    {
        return $this->state(fn (): array => ['state' => PartyState::Live]);
    }

    public function ended(): static
    {
        return $this->state(fn (): array => ['state' => PartyState::Ended]);
    }

    private function uniqueCode(): string
    {
        do {
            $code = Str::upper(Str::random(4));
        } while (! ctype_alpha($code) || Party::query()->where('code', $code)->exists());

        return $code;
    }

    /**
     * @param  array<string, mixed>  $theme
     */
    public function withTheme(array $theme): static
    {
        return $this->state(fn (): array => ['theme' => $theme]);
    }
}
