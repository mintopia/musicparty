<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('upcoming_songs', function (Blueprint $table) {
            $table->timestamp('not_before')->nullable()->default(null)->after('song_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('upcoming_songs', function (Blueprint $table) {
            $table->dropColumn(['not_before']);
        });
    }
};
