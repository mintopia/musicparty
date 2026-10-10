<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('party_mods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->string('mod_id', 64);
            $table->boolean('enabled')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['party_id', 'mod_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('party_mods');
    }
};
