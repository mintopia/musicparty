<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('party_mod_setting_events');
        Schema::dropIfExists('party_mod_settings');
        Schema::dropIfExists('mod_settings');
        Schema::dropIfExists('mods');
    }

    public function down(): void {}
};
