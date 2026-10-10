<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('play_ratings')) {
            $this->copyIntoRatings();
        }

        Schema::dropIfExists('play_ratings');
    }

    public function down(): void
    {
        Schema::create('play_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('track_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_member_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('value');
            $table->timestamps();

            $table->unique(['track_request_id', 'party_member_id']);
        });
    }

    private function copyIntoRatings(): void
    {
        $rows = Schema::getConnection()->table('play_ratings')
            ->join('plays', 'plays.track_request_id', '=', 'play_ratings.track_request_id')
            ->select('plays.id as play_id', 'play_ratings.party_member_id', 'play_ratings.value', 'play_ratings.created_at', 'play_ratings.updated_at')
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();

        foreach (array_chunk($rows, 500) as $chunk) {
            Schema::getConnection()->table('ratings')->insertOrIgnore($chunk);
        }
    }
};
