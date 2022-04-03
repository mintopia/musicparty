<?php

use App\Models\Song;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->unsignedBigInteger('song_id')->nullable()->default(null)->after('backup_playlist_id');
            $table->timestamp('song_started_at')->nullable()->default(null)->after('song_id');
            $table->foreign('song_id')->references('id')->on('songs')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->dropForeignIdFor(Song::class);
            $table->dropColumn(['song_id', 'song_started_at']);
        });
    }
};
