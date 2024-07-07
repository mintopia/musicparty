<?php

namespace Database\Seeders;

use App\Models\PartyMemberRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class PartyMemberRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            'owner' => 'Owner',
            'user' => 'User',
            'banned' => 'Banned',
        ];

        foreach ($roles as $code => $name) {
            $role = PartyMemberRole::whereCode($code)->first();
            if (!$role) {
                $role = new PartyMemberRole();
                $role->code = $code;
            }
            $role->name = $name;
            $role->save();
            Log::info("Updated {$role}");
        }
    }
}
