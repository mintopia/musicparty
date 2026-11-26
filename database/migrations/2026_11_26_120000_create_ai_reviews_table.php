<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('party_member_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('upcoming_song_id')->nullable()->constrained()->nullOnDelete();
            $table->string('track_provider');
            $table->string('track_id');
            $table->string('track_title');
            $table->string('track_artists');
            $table->string('driver');
            $table->string('model')->nullable();
            $table->string('endpoint_host')->nullable();
            $table->json('request');
            $table->json('response')->nullable();
            $table->string('outcome');
            $table->text('reason')->nullable();
            $table->double('score')->nullable();
            $table->double('confidence')->nullable();
            $table->unsignedInteger('latency_ms')->default(0);
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->string('error_class')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['party_id', 'created_at']);
            $table->index(['party_id', 'outcome']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_reviews');
    }
};
