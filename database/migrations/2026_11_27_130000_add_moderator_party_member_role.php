<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('party_member_roles')->where('code', 'moderator')->exists()) {
            return;
        }

        DB::table('party_member_roles')->insert([
            'code' => 'moderator',
            'name' => 'Moderator',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $roleId = DB::table('party_member_roles')->where('code', 'moderator')->value('id');
        $userRoleId = DB::table('party_member_roles')->where('code', 'user')->value('id');
        if ($roleId !== null && $userRoleId !== null) {
            DB::table('party_members')->where('role_id', $roleId)->update(['role_id' => $userRoleId]);
        }
        DB::table('party_member_roles')->where('code', 'moderator')->delete();
    }
};
