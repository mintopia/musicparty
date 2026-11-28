<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::delete('delete from song_ratings where id not in (select keep_id from (select max(id) as keep_id from song_ratings group by played_song_id, user_id) as keepers)');
        DB::update('update played_songs set rating = coalesce((select sum(value) from song_ratings where song_ratings.played_song_id = played_songs.id), 0)');

        Schema::table('song_ratings', function (Blueprint $table) {
            $table->unique(['played_song_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('song_ratings', function (Blueprint $table) {
            $table->index('played_song_id');
        });

        Schema::table('song_ratings', function (Blueprint $table) {
            $table->dropUnique(['played_song_id', 'user_id']);
        });
    }
};
