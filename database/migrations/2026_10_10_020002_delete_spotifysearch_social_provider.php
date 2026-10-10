<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CODE = 'spotifysearch';

    public function up(): void
    {
        $providerIds = DB::table('social_providers')->where('code', self::CODE)->pluck('id');

        if ($providerIds->isEmpty()) {
            return;
        }

        DB::table('linked_accounts')->whereIn('social_provider_id', $providerIds)->delete();
        DB::table('provider_settings')
            ->where('provider_type', 'App\\Models\\SocialProvider')
            ->whereIn('provider_id', $providerIds)
            ->delete();
        DB::table('social_providers')->whereIn('id', $providerIds)->delete();
    }

    public function down(): void {}
};
