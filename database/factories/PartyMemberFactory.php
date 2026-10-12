<?php

namespace Database\Factories;

use App\Models\Party;
use App\Models\PartyMember;
use App\Models\PartyMemberRole;
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
            'user_id' => User::factory(),
            'party_id' => Party::factory(),
            'banned' => false,
            'role_id' => fn () => self::roleId('user'),
        ];
    }

    public function owner(): static
    {
        return $this->state(['role_id' => fn () => self::roleId('owner')]);
    }

    public function vip(): static
    {
        return $this->state(['role_id' => fn () => self::roleId('vip')]);
    }

    public function user(): static
    {
        return $this->state(['role_id' => fn () => self::roleId('user')]);
    }

    public function banned(): static
    {
        return $this->state(['role_id' => fn () => self::roleId('banned'), 'banned' => true]);
    }

    protected static function roleId(string $code): int
    {
        return PartyMemberRole::query()->firstOrCreate(
            ['code' => $code],
            ['name' => ucfirst($code) === 'Vip' ? 'VIP' : ucfirst($code)]
        )->id;
    }
}
