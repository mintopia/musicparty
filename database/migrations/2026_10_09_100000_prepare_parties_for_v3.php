<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->string('music_provider', 32)->nullable();
            $table->string('player_kind', 32)->nullable();
            $table->string('state', 16)->default('paused');
            $table->unique('code');
        });

        Schema::table('party_members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });

        Schema::table('party_members', function (Blueprint $table) {
            $table->string('role', 16)->default('guest');
            $table->unique(['party_id', 'user_id']);
        });

        Schema::dropIfExists('party_member_roles');
    }

    public function down(): void
    {
        Schema::create('party_member_roles', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::table('party_members', function (Blueprint $table) {
            $table->dropUnique(['party_id', 'user_id']);
            $table->dropColumn('role');
        });

        Schema::table('party_members', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->constrained('party_member_roles')->cascadeOnDelete();
        });

        Schema::table('parties', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn(['music_provider', 'player_kind', 'state']);
        });
    }
};
