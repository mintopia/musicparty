<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('upcoming_songs', function (Blueprint $table) {
            $table->string('sent_track_id')->nullable()->after('queued_at');
        });
    }

    public function down(): void
    {
        Schema::table('upcoming_songs', function (Blueprint $table) {
            $table->dropColumn('sent_track_id');
        });
    }
};
