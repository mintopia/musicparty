<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, string> */
    private const TABLES = [
        'songs' => 'provider_track_id',
        'albums' => 'provider_album_id',
        'artists' => 'provider_artist_id',
    ];

    public function up(): void
    {
        foreach (array_keys(self::TABLES) as $table) {
            if (! Schema::hasColumn($table, 'spotify_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (Schema::hasIndex($table, "{$table}_spotify_id_unique")) {
                    $blueprint->dropUnique(['spotify_id']);
                }
            });
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('spotify_id'));
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table => $column) {
            if (Schema::hasColumn($table, 'spotify_id')) {
                continue;
            }

            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->string('spotify_id')->nullable()->after('id'));
            DB::table($table)->where('provider_id', 'spotify')->update(['spotify_id' => DB::raw($column)]);
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unique('spotify_id'));
        }
    }
};
