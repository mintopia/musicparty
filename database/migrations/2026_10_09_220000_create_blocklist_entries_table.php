<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocklist_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->string('match_type');
            $table->string('value', 500);
            $table->boolean('is_regex')->default(false);
            $table->boolean('is_enabled')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['party_id', 'is_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocklist_entries');
    }
};
