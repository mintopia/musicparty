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
        Schema::table('users', function(Blueprint $table) {
            $table->longText('status')->after('admin')->nullable();
            $table->timestamp('status_updated_at')->after('status')->nullable();
        });

        Schema::table('parties', function (Blueprint $table) {
            $table->timestamp('history_updated_at')->nullable()->after('backup_playlist_id');
            $table->dropColumn('status');
        });

        Schema::table('songs', function (Blueprint $table) {
            $table->dropColumn(['artist', 'artist_id', 'album', 'album_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function(Blueprint $table) {
            $table->dropColumn(['status', 'status_updated_at']);
        });
        Schema::table('parties', function(Blueprint $table) {
            $table->dropColumn('history_updated_at');
            $table->longText('status')->nullable()->after('backup_playlist_id');
        });
        Schema::table('songs', function(Blueprint $table) {
            $table->string('artist')->after('name');
            $table->string('artist_id')->after('artist');
            $table->string('album')->after('artist_id');
            $table->string('album_id')->after('album');
        });
    }
};
