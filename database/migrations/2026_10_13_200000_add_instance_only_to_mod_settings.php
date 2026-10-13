<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mod_settings', function (Blueprint $table) {
            $table->boolean('instance_only')->default(false)->after('private');
            $table->longText('instance_value')->nullable()->after('default');
        });
    }

    public function down(): void
    {
        Schema::table('mod_settings', function (Blueprint $table) {
            $table->dropColumn(['instance_only', 'instance_value']);
        });
    }
};
