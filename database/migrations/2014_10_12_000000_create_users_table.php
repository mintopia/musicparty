<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nickname');
            $table->string('discord_name');
            $table->string('email');
            $table->string('avatar')->nullable()->default(null);

            $table->string('discord_id');
            $table->string('discord_access_token');
            $table->string('discord_refresh_token');
            $table->timestamp('discord_token_expires_at');

            $table->string('spotify_id')->nullable();
            $table->longText('spotify_access_token')->nullable();
            $table->longText('spotify_refresh_token')->nullable();
            $table->timestamp('spotify_token_expires_at')->nullable();

            $table->boolean('can_create_party')->default(false);
            $table->boolean('admin')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
};
