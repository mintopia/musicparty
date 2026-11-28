<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $duplicates = DB::table('linked_accounts')
                ->select('social_provider_id', 'external_id')
                ->whereNotNull('social_provider_id')
                ->whereNotNull('external_id')
                ->groupBy('social_provider_id', 'external_id')
                ->havingRaw('count(*) > 1')
                ->get();

            foreach ($duplicates as $duplicate) {
                $rows = DB::table('linked_accounts')
                    ->where('social_provider_id', $duplicate->social_provider_id)
                    ->where('external_id', $duplicate->external_id)
                    ->orderByDesc('id')
                    ->get(['id', 'user_id']);

                $removed = $rows->slice(1);
                $userIds = $rows->pluck('user_id')->unique();

                $context = [
                    'social_provider_id' => $duplicate->social_provider_id,
                    'external_id' => $duplicate->external_id,
                    'kept_id' => $rows->pluck('id')->first(),
                    'removed_ids' => $removed->pluck('id')->values()->all(),
                    'user_ids' => $userIds->values()->all(),
                ];

                if ($userIds->count() > 1) {
                    Log::warning('Linked account duplicate spans different users', $context);
                }
                Log::warning('Deleted duplicate linked accounts', $context);

                DB::table('linked_accounts')->whereIn('id', $removed->pluck('id'))->delete();
            }
        });

        Schema::table('linked_accounts', function (Blueprint $table) {
            $table->unique(['social_provider_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('linked_accounts', function (Blueprint $table) {
            $table->index('social_provider_id');
        });

        Schema::table('linked_accounts', function (Blueprint $table) {
            $table->dropUnique(['social_provider_id', 'external_id']);
        });
    }
};
