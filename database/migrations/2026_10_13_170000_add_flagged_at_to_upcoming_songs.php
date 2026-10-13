<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('upcoming_songs', function (Blueprint $table) {
            $table->timestamp('flagged_at')->nullable()->default(null)->after('not_before');
        });
    }

    public function down(): void
    {
        Schema::table('upcoming_songs', function (Blueprint $table) {
            $table->dropColumn('flagged_at');
        });
    }
};
