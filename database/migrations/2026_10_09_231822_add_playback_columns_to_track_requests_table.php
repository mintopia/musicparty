<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('track_requests', function (Blueprint $table) {
            $table->foreignId('party_member_id')->nullable()->change();
            $table->timestamp('not_before')->nullable();
            $table->timestamp('up_next_at')->nullable();
            $table->timestamp('enqueued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->string('selection_mode', 16)->nullable();
            $table->integer('selection_score')->nullable();
            $table->index(['party_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('track_requests', function (Blueprint $table) {
            $table->dropIndex(['party_id', 'status']);
            $table->dropColumn(['not_before', 'up_next_at', 'enqueued_at', 'started_at', 'selection_mode', 'selection_score']);
        });
    }
};
