<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mod_whamageddon_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('party_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('request_id')->nullable()->constrained('track_requests')->nullOnDelete();
            $table->integer('accumulated_bonus')->default(0);
            $table->timestamp('last_played_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mod_whamageddon_states');
    }
};
