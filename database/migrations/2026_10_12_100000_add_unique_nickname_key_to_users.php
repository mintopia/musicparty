<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MAX_LENGTH = 32;

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('nickname_key', self::MAX_LENGTH)->nullable()->after('nickname');
        });

        $taken = [];
        $rows = DB::table('users')->orderBy('id')->get(['id', 'nickname']);

        foreach ($rows as $row) {
            $nickname = mb_substr(trim((string) $row->nickname), 0, self::MAX_LENGTH);
            $key = mb_strtolower($nickname);

            if ($key === '' || isset($taken[$key])) {
                $base = $nickname === '' ? 'Player' : $nickname;
                $suffixNumber = 2;
                do {
                    $suffix = ' '.$suffixNumber;
                    $nickname = rtrim(mb_substr($base, 0, self::MAX_LENGTH - mb_strlen($suffix))).$suffix;
                    $key = mb_strtolower($nickname);
                    $suffixNumber++;
                } while (isset($taken[$key]));
            }

            $taken[$key] = true;

            if ($nickname !== $row->nickname) {
                DB::table('users')->where('id', $row->id)->update(['nickname' => $nickname]);
                $this->recordRename($row->id, (string) $row->nickname, $nickname);
            }

            DB::table('users')->where('id', $row->id)->update(['nickname_key' => $key]);
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->unique('nickname_key');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['nickname_key']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('nickname_key');
        });
    }

    private function recordRename(int $userId, string $old, string $new): void
    {
        DB::table('admin_audit_entries')->insert([
            'admin_id' => null,
            'admin_name' => 'System',
            'action' => 'user.nickname_deduplicated',
            'subject_user_id' => $userId,
            'subject_name' => $new,
            'meta' => json_encode(['old' => $old, 'new' => $new]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
