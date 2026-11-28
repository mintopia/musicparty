<?php

use App\Models\PartyMember;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array ROLE_PRIVILEGE = ['owner', 'moderator', 'vip', 'user', 'banned'];

    public function up(): void
    {
        $this->mergeDuplicateMembers();

        Schema::table('party_members', function (Blueprint $table) {
            $table->unique(['party_id', 'user_id']);
            $table->dropColumn(['canvote', 'banned']);
        });
    }

    public function down(): void
    {
        Schema::table('party_members', function (Blueprint $table) {
            $table->dropUnique(['party_id', 'user_id']);
            $table->boolean('canvote')->default(true);
            $table->boolean('banned')->default(false);
        });

        $bannedRoleId = DB::table('party_member_roles')->where('code', 'banned')->value('id');
        if ($bannedRoleId !== null) {
            DB::table('party_members')->where('role_id', $bannedRoleId)->update(['banned' => true]);
        }
    }

    private function mergeDuplicateMembers(): void
    {
        $privilege = DB::table('party_member_roles')->pluck('code', 'id')
            ->map(fn (string $code): int => array_search($code, self::ROLE_PRIVILEGE, true) ?: ($code === 'owner' ? 0 : PHP_INT_MAX))
            ->all();
        $morphClass = (new PartyMember)->getMorphClass();

        $duplicates = DB::table('party_members')
            ->select('party_id', 'user_id')
            ->groupBy('party_id', 'user_id')
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $rows = DB::table('party_members')
                ->where('party_id', $duplicate->party_id)
                ->where('user_id', $duplicate->user_id)
                ->orderBy('id')
                ->get(['id', 'role_id'])
                ->sortBy(fn (object $row): int => $privilege[$row->role_id] ?? PHP_INT_MAX)
                ->values();

            $keep = $rows->first();
            if ($keep === null) {
                continue;
            }

            $keepId = $keep->id;

            foreach ($rows->slice(1) as $loser) {
                DB::table('ai_reviews')->where('party_member_id', $loser->id)->update(['party_member_id' => $keepId]);

                $keptKeys = DB::table('mod_data')->where('owner_type', $morphClass)->where('owner_id', $keepId)->get(['mod_code', 'key'])
                    ->map(fn (object $row): string => $row->mod_code.'|'.$row->key)->all();
                $loserData = DB::table('mod_data')->where('owner_type', $morphClass)->where('owner_id', $loser->id)->get(['id', 'mod_code', 'key']);
                foreach ($loserData as $data) {
                    if (in_array($data->mod_code.'|'.$data->key, $keptKeys, true)) {
                        DB::table('mod_data')->where('id', $data->id)->delete();
                    } else {
                        DB::table('mod_data')->where('id', $data->id)->update(['owner_id' => $keepId]);
                    }
                }

                DB::table('party_members')->where('id', $loser->id)->delete();
            }
        }
    }
};
