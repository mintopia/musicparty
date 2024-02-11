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
            $table->foreignId('user_id')->nullable()->default(null)->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('upcoming_songs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
