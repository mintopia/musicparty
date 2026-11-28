<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('upcoming_songs', function (Blueprint $table) {
            $table->index(['party_id', 'queued_at', 'flagged_at', 'score'], 'upcoming_songs_queue_listing_index');
        });
    }

    public function down(): void
    {
        Schema::table('upcoming_songs', function (Blueprint $table) {
            $table->dropIndex('upcoming_songs_queue_listing_index');
        });
    }
};
