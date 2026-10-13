<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->string('playback_source')->nullable();
            $table->timestamp('playback_received_at', 3)->nullable();
            $table->timestamp('playback_sampled_at', 3)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->dropColumn(['playback_source', 'playback_received_at', 'playback_sampled_at']);
        });
    }
};
