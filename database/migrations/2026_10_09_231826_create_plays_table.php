<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->foreignId('track_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('party_member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider_track_id');
            $table->string('title');
            $table->json('artists');
            $table->string('album')->nullable();
            $table->string('artwork_url', 2048)->nullable();
            $table->unsignedInteger('duration_ms');
            $table->boolean('explicit')->default(false);
            $table->string('selection_mode', 16)->nullable();
            $table->integer('selection_score')->nullable();
            $table->timestamp('played_at');
            $table->timestamps();

            $table->index(['party_id', 'played_at']);
            $table->index(['party_id', 'provider_track_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plays');
    }
};
