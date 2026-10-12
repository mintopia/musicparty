<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('linked_accounts', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->after('email');
        });

        DB::table('linked_accounts')
            ->whereIn('social_provider_id', DB::table('social_providers')->where('code', 'twitch')->select('id'))
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('linked_accounts', function (Blueprint $table) {
            $table->dropColumn('email_verified_at');
        });
    }
};
