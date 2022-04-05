<?php

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
            $table->timestamp('state_updated_at')->nullable()->default(null)->after('song_started_at');
            $table->dropColumn('history_updated_at');
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
            $table->timestamp('history_updated_at')->nullable()->after('backup_playlist_id');
            $table->dropColumn('state_updated_at');
        });
    }
};
