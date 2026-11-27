<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('party_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('query', 120);
            $table->string('term', 120);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['party_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('party_searches');
    }
};
