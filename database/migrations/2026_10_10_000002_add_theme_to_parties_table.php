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
        Schema::table('parties', function (Blueprint $table) {
            $table->json('theme')->nullable();
            $table->string('theme_logo_path')->nullable();
            $table->string('theme_background_path')->nullable();
            $table->string('tv_layout')->default('default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->dropColumn(['theme', 'theme_logo_path', 'theme_background_path', 'tv_layout']);
        });
    }
};
