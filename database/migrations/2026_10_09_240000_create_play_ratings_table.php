<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('play_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('track_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_member_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('value');
            $table->timestamps();

            $table->unique(['track_request_id', 'party_member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('play_ratings');
    }
};
