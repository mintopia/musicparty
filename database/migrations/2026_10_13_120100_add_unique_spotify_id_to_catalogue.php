<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->mergeDuplicates('albums', [['songs', 'album_id']]);
        $this->mergeDuplicates('artists', [['artist_song', 'artist_id']]);
        $this->mergeDuplicates('songs', [
            ['upcoming_songs', 'song_id'],
            ['played_songs', 'song_id'],
            ['parties', 'song_id'],
            ['artist_song', 'song_id'],
        ]);
        $this->mergeDuplicateArtistSongLinks();

        foreach (['songs', 'albums', 'artists'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unique('spotify_id');
            });
        }
    }

    public function down(): void
    {
        foreach (['songs', 'albums', 'artists'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropUnique(['spotify_id']);
            });
        }
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $references  Table and foreign key column pairs pointing at $table.
     */
    private function mergeDuplicates(string $table, array $references): void
    {
        $duplicates = DB::table($table)
            ->select('spotify_id', DB::raw('MIN(id) as keep_id'))
            ->groupBy('spotify_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $losers = DB::table($table)
                ->where('spotify_id', $duplicate->spotify_id)
                ->where('id', '<>', $duplicate->keep_id)
                ->pluck('id')
                ->all();

            foreach ($references as [$referencingTable, $column]) {
                DB::table($referencingTable)
                    ->whereIn($column, $losers)
                    ->update([$column => $duplicate->keep_id]);
            }

            DB::table($table)->whereIn('id', $losers)->delete();
        }
    }

    private function mergeDuplicateArtistSongLinks(): void
    {
        $duplicates = DB::table('artist_song')
            ->select('artist_id', 'song_id', DB::raw('MIN(id) as keep_id'))
            ->groupBy('artist_id', 'song_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('artist_song')
                ->where('artist_id', $duplicate->artist_id)
                ->where('song_id', $duplicate->song_id)
                ->where('id', '<>', $duplicate->keep_id)
                ->delete();
        }
    }
};
