<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('song_ratings');
        Schema::dropIfExists('votes');
        Schema::dropIfExists('artist_song');
        Schema::dropIfExists('played_songs');
        Schema::dropIfExists('upcoming_songs');

        if (Schema::hasColumn('parties', 'song_id')) {
            Schema::table('parties', function (Blueprint $table) {
                $table->dropConstrainedForeignId('song_id');
            });
        }

        Schema::dropIfExists('songs');
        Schema::dropIfExists('albums');
        Schema::dropIfExists('artists');
        Schema::dropIfExists('party_moderations');
        Schema::dropIfExists('themes');
        Schema::dropIfExists('websockets_statistics_entries');
        Schema::dropIfExists('play_ratings');
        Schema::dropIfExists('party_logs');
    }

    public function down(): void {}
};
