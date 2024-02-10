<?php

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
        Schema::create('provider_settings', function (Blueprint $table) {
            $table->id();
            $table->morphs('provider');
            $table->string('name');
            $table->string('code');
            $table->string('description')->nullable()->default(null);
            $table->string('type');
            $table->boolean('encrypted')->default(false);
            $table->string('validation')->nullable()->default(null);
            $table->longText('value')->nullable()->default(null);
            $table->integer('order');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_settings');
    }
};
