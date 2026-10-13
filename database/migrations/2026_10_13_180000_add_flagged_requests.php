<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->boolean('flag_requests')->default(false);
        });
        Schema::table('upcoming_songs', function (Blueprint $table) {
            $table->string('flag_reason')->nullable()->default(null)->after('flagged_at');
        });
        Schema::table('party_moderations', function (Blueprint $table) {
            $table->string('action')->default('reject')->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('party_moderations', function (Blueprint $table) {
            $table->dropColumn('action');
        });
        Schema::table('upcoming_songs', function (Blueprint $table) {
            $table->dropColumn('flag_reason');
        });
        Schema::table('parties', function (Blueprint $table) {
            $table->dropColumn('flag_requests');
        });
    }
};
