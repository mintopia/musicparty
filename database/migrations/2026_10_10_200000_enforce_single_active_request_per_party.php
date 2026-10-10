<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('track_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('up_next_party_id')->nullable()->virtualAs("CASE WHEN status = 'up_next' THEN party_id END");
            $table->unsignedBigInteger('playing_party_id')->nullable()->virtualAs("CASE WHEN status = 'playing' THEN party_id END");
            $table->unique('up_next_party_id');
            $table->unique('playing_party_id');
        });
    }

    public function down(): void
    {
        Schema::table('track_requests', function (Blueprint $table) {
            $table->dropUnique(['up_next_party_id']);
            $table->dropUnique(['playing_party_id']);
            $table->dropColumn(['up_next_party_id', 'playing_party_id']);
        });
    }
};
