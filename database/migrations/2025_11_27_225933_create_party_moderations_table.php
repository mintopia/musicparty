<?php

use App\Models\Party;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('party_moderations', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Party::class)->constrained()->cascadeOnDelete();
            $table->string('type')->default('mtName');
            $table->string('value');
            $table->string('notes')->nullable()->default(null);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('party_moderations');
    }
};
