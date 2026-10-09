<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('play_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_member_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('value');
            $table->timestamps();

            $table->unique(['play_id', 'party_member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
