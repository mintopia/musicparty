<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('track_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_member_id')->constrained()->cascadeOnDelete();
            $table->string('provider_track_id');
            $table->string('title');
            $table->json('artists');
            $table->string('album')->nullable();
            $table->string('artwork_url', 2048)->nullable();
            $table->unsignedInteger('duration_ms');
            $table->boolean('explicit')->default(false);
            $table->string('status')->default('queued');
            $table->timestamps();

            $table->index(['party_id', 'status', 'provider_track_id']);
        });

        Schema::create('request_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('track_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_member_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('value');
            $table->timestamps();

            $table->unique(['track_request_id', 'party_member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_votes');
        Schema::dropIfExists('track_requests');
    }
};
