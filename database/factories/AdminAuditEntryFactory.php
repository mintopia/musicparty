<?php

namespace Database\Factories;

use App\Domain\Admin\Models\AdminAuditEntry;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminAuditEntry>
 */
class AdminAuditEntryFactory extends Factory
{
    protected $model = AdminAuditEntry::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'admin_id' => User::factory(),
            'action' => 'user.suspended',
            'subject_user_id' => null,
            'meta' => null,
        ];
    }
}
