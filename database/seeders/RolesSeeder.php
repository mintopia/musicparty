<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class RolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            'admin' => 'Admin',
            'create-party' => 'Create Party',
        ];

        foreach ($roles as $code => $name) {
            $role = Role::whereCode($code)->first();
            if (!$role) {
                $role = new Role();
                $role->code = $code;
            }
            $role->name = $name;
            $role->save();
            Log::info("Updated {$role}");
        }
    }
}
