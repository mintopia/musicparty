<?php

namespace Database\Factories;

use App\Domain\Admin\Models\Role;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nickname' => mb_substr(fake()->unique()->userName(), 0, 24).' '.fake()->unique()->numberBetween(100, 999999),
            'first_login' => false,
            'terms_agreed_at' => now(),
            'suspended' => false,
        ];
    }

    public function firstLogin(): static
    {
        return $this->state(fn (): array => ['first_login' => true, 'terms_agreed_at' => null]);
    }

    public function withRole(string $code): static
    {
        return $this->afterCreating(function (User $user) use ($code): void {
            $role = Role::query()->where('code', $code)->first() ?? Role::query()->forceCreate(['code' => $code, 'name' => $code]);
            $user->roles()->syncWithoutDetaching([$role->id]);
        });
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => ['suspended' => true]);
    }
}
