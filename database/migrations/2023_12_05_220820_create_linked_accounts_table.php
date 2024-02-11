<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('linked_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_provider_id')->nullable()->default(null)->contrained()->cascadeOnDelete();
            $table->string('external_id')->nullable()->default(null);
            $table->string('name')->nullable()->default(null);
            $table->longText('avatar_url')->nullable()->default(null);
            $table->longText('email')->nullable()->default(null);
            $table->longText('access_token')->nullable()->default(null);
            $table->longText('refresh_token')->nullable()->default(null);
            $table->timestamp('access_token_expires_at')->nullable()->default(null);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('linked_accounts');
    }
};
