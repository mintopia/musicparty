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
        Schema::table('parties', function (Blueprint $table) {
            $table->boolean('trustscore')->default(false)->after('weighted');
            $table->unsignedBigInteger('trusted_user_id')->nullable()->default(null)->after('trustscore');
            $table->foreign('trusted_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('party_members', function (Blueprint $table) {
            $table->float('trustscore')->default(0)->after('banned');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->dropForeign('parties_trusted_user_id_foreign');
            $table->dropColumn(['trustscore', 'trusted_user_id']);
        });

        Schema::table('party_members', function (Blueprint $table) {
            $table->dropColumn('trustscore');
        });
    }
};
