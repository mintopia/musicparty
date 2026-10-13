<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->string('country', 2)->default('GB')->after('name');
        });

        DB::table('parties')->update([
            'country' => DB::raw("COALESCE((SELECT UPPER(NULLIF(TRIM(users.market), '')) FROM users WHERE users.id = parties.user_id), 'GB')"),
        ]);
    }

    public function down(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->dropColumn('country');
        });
    }
};
