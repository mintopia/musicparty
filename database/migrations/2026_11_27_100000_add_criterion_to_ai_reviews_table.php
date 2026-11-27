<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_reviews', function (Blueprint $table): void {
            $table->string('criterion')->nullable()->after('confidence');
            $table->double('criterion_confidence')->nullable()->after('criterion');
        });
    }

    public function down(): void
    {
        Schema::table('ai_reviews', function (Blueprint $table): void {
            $table->dropColumn(['criterion', 'criterion_confidence']);
        });
    }
};
