<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $keep = DB::table('role_user')->selectRaw('MIN(id) as id')->groupBy('user_id', 'role_id')->pluck('id');
        DB::table('role_user')->whereNotIn('id', $keep)->delete();

        Schema::table('role_user', function (Blueprint $table): void {
            $table->unique(['user_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::table('role_user', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'role_id']);
        });
    }
};
