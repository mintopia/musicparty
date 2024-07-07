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
        Schema::create('parties', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('name');

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->boolean('active')->default(true);
            $table->boolean('queue')->default(false);
            $table->boolean('force')->default(false);
            $table->boolean('explicit')->default(true);
            $table->boolean('allow_requests')->default(true);
            $table->boolean('process_requests')->default(true);
            $table->boolean('downvotes')->default(true);

            $table->integer('max_song_length')->nullable()->default(null);

            $table->string('device_id')->nullable()->default(null);
            $table->string('device_name')->nullable()->default(null);
            $table->string('playlist_id')->nullable()->default(null);
            $table->string('backup_playlist_id')->nullable()->default(null);
            $table->string('backup_playlist_name')->nullable()->default(null);

            $table->timestamp('last_updated_at')->nullable()->default(null);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parties');
    }
};
