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
        Schema::table('played_songs', function (Blueprint $table) {
            $table->integer('rating')->default(0);
            $table->string('relinked_from')->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('played_songs', function (Blueprint $table) {
            $table->dropColumn(['rating', 'relinked_from']);
        });
    }
};
