<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $affected = [];
        $duplicates = DB::table('votes')
            ->select('upcoming_song_id', 'user_id', DB::raw('MAX(id) as keep_id'))
            ->groupBy('upcoming_song_id', 'user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('votes')
                ->where('upcoming_song_id', $duplicate->upcoming_song_id)
                ->where('user_id', $duplicate->user_id)
                ->where('id', '<>', $duplicate->keep_id)
                ->delete();
            $affected[] = $duplicate->upcoming_song_id;
        }

        foreach (array_unique($affected) as $upcomingSongId) {
            DB::table('upcoming_songs')
                ->where('id', $upcomingSongId)
                ->update(['score' => DB::raw('COALESCE((SELECT SUM(value) FROM votes WHERE votes.upcoming_song_id = upcoming_songs.id), 0) + score_adjustment')]);
        }

        Schema::table('votes', function (Blueprint $table) {
            $table->unique(['upcoming_song_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('votes', function (Blueprint $table) {
            $table->dropUnique(['upcoming_song_id', 'user_id']);
        });
    }
};
