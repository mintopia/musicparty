<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mod_settings', function (Blueprint $table): void {
            $table->json('options')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mod_settings', function (Blueprint $table): void {
            $table->dropColumn('options');
        });
    }
};
