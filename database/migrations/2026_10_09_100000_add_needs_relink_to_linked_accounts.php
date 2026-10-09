<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('linked_accounts', function (Blueprint $table) {
            $table->boolean('needs_relink')->default(false)->after('access_token_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('linked_accounts', function (Blueprint $table) {
            $table->dropColumn('needs_relink');
        });
    }
};
