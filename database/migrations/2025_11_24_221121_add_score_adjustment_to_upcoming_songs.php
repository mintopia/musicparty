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
            $table->integer('score_adjustment')->default(0)->after('score');
            $table->string('fallback_override')->nullable()->default(null)->after('score_adjustment');
            $table->string('css_classes')->nullable()->default(null)->after('fallback_override');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('upcoming_songs', function (Blueprint $table) {
            $table->dropColumns([
                'score_adjustment', 'fallback_override', 'css_classes',
            ]);
        });
    }
};
