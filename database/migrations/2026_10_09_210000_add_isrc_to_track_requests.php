<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('track_requests', function (Blueprint $table) {
            $table->string('isrc', 12)->nullable()->after('provider_track_id');
            $table->index(['party_id', 'isrc']);
        });
    }

    public function down(): void
    {
        Schema::table('track_requests', function (Blueprint $table) {
            $table->dropIndex(['party_id', 'isrc']);
            $table->dropColumn('isrc');
        });
    }
};
