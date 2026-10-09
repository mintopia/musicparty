<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('upcoming_songs', function (Blueprint $table) {
            $table->dropColumn('css_classes');
        });
    }

    public function down(): void
    {
        Schema::table('upcoming_songs', function (Blueprint $table) {
            $table->string('css_classes')->nullable()->default(null)->after('fallback_override');
        });
    }
};
