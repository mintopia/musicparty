<?php

namespace Database\Factories;

use App\Domain\Admin\IntegrationAbility;
use App\Domain\Admin\Models\IntegrationToken;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<IntegrationToken>
 */
class IntegrationTokenFactory extends Factory
{
    protected $model = IntegrationToken::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'token_hash' => IntegrationToken::hashFor(Str::random(40)),
            'abilities' => [IntegrationAbility::Read->value],
            'last_used_at' => null,
            'revoked_at' => null,
            'created_by' => User::factory(),
        ];
    }

    public function withPlainText(string $plainText): static
    {
        return $this->state(['token_hash' => IntegrationToken::hashFor($plainText)]);
    }

    /**
     * @param  list<string>  $abilities
     */
    public function withAbilities(array $abilities): static
    {
        return $this->state(['abilities' => $abilities]);
    }

    public function revoked(): static
    {
        return $this->state(['revoked_at' => now()]);
    }
}
