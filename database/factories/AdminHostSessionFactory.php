<?php

namespace Database\Factories;

use App\Domain\Admin\Models\AdminHostSession;
use App\Domain\Identity\Models\User;
use App\Domain\Party\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminHostSession>
 */
class AdminHostSessionFactory extends Factory
{
    protected $model = AdminHostSession::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'party_id' => Party::factory(),
            'expires_at' => now()->addMinutes(config()->integer('musicparty.act_as_host_ttl_minutes')),
        ];
    }
}
