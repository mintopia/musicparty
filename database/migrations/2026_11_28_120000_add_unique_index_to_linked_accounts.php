<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('linked_accounts')
            ->select('social_provider_id', 'external_id')
            ->whereNotNull('social_provider_id')
            ->whereNotNull('external_id')
            ->groupBy('social_provider_id', 'external_id')
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $keepId = DB::table('linked_accounts')
                ->where('social_provider_id', $duplicate->social_provider_id)
                ->where('external_id', $duplicate->external_id)
                ->min('id');

            DB::table('linked_accounts')
                ->where('social_provider_id', $duplicate->social_provider_id)
                ->where('external_id', $duplicate->external_id)
                ->where('id', '!=', $keepId)
                ->delete();
        }

        Schema::table('linked_accounts', function (Blueprint $table) {
            $table->unique(['social_provider_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('linked_accounts', function (Blueprint $table) {
            $table->dropUnique(['social_provider_id', 'external_id']);
        });
    }
};
