<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('played_songs', function (Blueprint $table): void {
            $table->index(['party_id', 'played_at']);
        });
    }

    public function down(): void
    {
        Schema::table('played_songs', function (Blueprint $table): void {
            $table->dropIndex(['party_id', 'played_at']);
        });
    }
};
