<?php

namespace Database\Factories;

use App\Models\PartyMemberRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartyMemberRole>
 */
class PartyMemberRoleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'user',
            'name' => 'User',
        ];
    }

    public function owner(): static
    {
        return $this->state(['code' => 'owner', 'name' => 'Owner']);
    }

    public function vip(): static
    {
        return $this->state(['code' => 'vip', 'name' => 'VIP']);
    }

    public function user(): static
    {
        return $this->state(['code' => 'user', 'name' => 'User']);
    }

    public function banned(): static
    {
        return $this->state(['code' => 'banned', 'name' => 'Banned']);
    }
}
