<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mod_trust_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_member_id')->unique()->constrained()->cascadeOnDelete();
            $table->double('score')->default(0);
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->index('party_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mod_trust_scores');
    }
};
