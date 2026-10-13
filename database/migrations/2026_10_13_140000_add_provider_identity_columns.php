<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public int $chunkSize = 1000;

    /** @var array<string, string> */
    private const TABLES = [
        'songs' => 'provider_track_id',
        'albums' => 'provider_album_id',
        'artists' => 'provider_artist_id',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => $column) {
            $index = $this->indexName($table, $column);

            Schema::table($table, function (Blueprint $blueprint) use ($table, $column) {
                if (! Schema::hasColumn($table, 'provider_id')) {
                    $blueprint->string('provider_id')->nullable()->after('id');
                }
                if (! Schema::hasColumn($table, $column)) {
                    $blueprint->string($column)->nullable()->after('provider_id');
                }
            });

            DB::table($table)
                ->whereNull($column)
                ->chunkById($this->chunkSize, function ($rows) use ($table, $column): void {
                    DB::table($table)
                        ->whereIn('id', $rows->pluck('id'))
                        ->update(['provider_id' => 'spotify', $column => DB::raw('spotify_id')]);
                });

            if (! Schema::hasIndex($table, $index)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unique(['provider_id', $column], $index));
            }
        }

        if (! Schema::hasColumn('parties', 'provider_id')) {
            Schema::table('parties', fn (Blueprint $table) => $table->string('provider_id')->default('spotify'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('parties', 'provider_id')) {
            Schema::table('parties', fn (Blueprint $table) => $table->dropColumn('provider_id'));
        }

        foreach (self::TABLES as $table => $column) {
            $index = $this->indexName($table, $column);

            if (Schema::hasIndex($table, $index)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropUnique($index));
            }

            foreach (['provider_id', $column] as $name) {
                if (Schema::hasColumn($table, $name)) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn($name));
                }
            }
        }
    }

    private function indexName(string $table, string $column): string
    {
        return "{$table}_provider_id_{$column}_unique";
    }
};
