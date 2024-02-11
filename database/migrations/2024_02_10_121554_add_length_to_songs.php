<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('songs', function (Blueprint $table) {
            $table->integer('length')->after('name');
            $table->dropColumn(['album', 'artist']);
            $table->dropConstrainedForeignId('artist_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('songs', function (Blueprint $table) {
            $table->dropColumn(['length']);
            $table->string('artist')->after('name');
            $table->string('album')->after('artist');

            $table->foreignId('artist_id')->after('album_id')->constrained()->cascadeOnDelete();
        });
    }
};
