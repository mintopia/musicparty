<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('track_requests', function (Blueprint $table) {
            $table->timestamp('score_changed_at')->nullable();
        });

        DB::table('track_requests')->update(['score_changed_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('track_requests', function (Blueprint $table) {
            $table->dropColumn('score_changed_at');
        });
    }
};
