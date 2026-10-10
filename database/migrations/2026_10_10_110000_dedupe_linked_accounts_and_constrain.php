<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->clearOrphanedProviders();
        $this->mergeDuplicates();

        Schema::table('linked_accounts', function (Blueprint $table) {
            $table->unique(['social_provider_id', 'external_id']);
            $table->foreign('social_provider_id')->references('id')->on('social_providers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('linked_accounts', function (Blueprint $table) {
            $table->dropForeign(['social_provider_id']);
            $table->dropUnique(['social_provider_id', 'external_id']);
        });
    }

    private function clearOrphanedProviders(): void
    {
        DB::table('linked_accounts')
            ->whereNotNull('social_provider_id')
            ->whereNotIn('social_provider_id', DB::table('social_providers')->select('id'))
            ->update(['social_provider_id' => null]);
    }

    private function mergeDuplicates(): void
    {
        $groups = DB::table('linked_accounts')
            ->whereNotNull('social_provider_id')
            ->whereNotNull('external_id')
            ->select('social_provider_id', 'external_id')
            ->groupBy('social_provider_id', 'external_id')
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($groups as $group) {
            $accounts = DB::table('linked_accounts')
                ->where('social_provider_id', $group->social_provider_id)
                ->where('external_id', $group->external_id)
                ->orderBy('id')
                ->get(['id', 'user_id']);

            $keptUserId = $accounts->first()->user_id;

            foreach ($accounts->skip(1) as $duplicate) {
                if ($duplicate->user_id !== $keptUserId) {
                    $this->reattach($duplicate->user_id, $keptUserId);
                }

                DB::table('linked_accounts')->where('id', $duplicate->id)->delete();
            }
        }
    }

    private function reattach(int $fromUserId, int $toUserId): void
    {
        DB::table('parties')->where('user_id', $fromUserId)->update(['user_id' => $toUserId]);

        foreach (['party_members', 'admin_host_sessions'] as $table) {
            $alreadyIn = DB::table($table)->where('user_id', $toUserId)->select('party_id');

            DB::table($table)->where('user_id', $fromUserId)->whereIn('party_id', $alreadyIn)->delete();
            DB::table($table)->where('user_id', $fromUserId)->update(['user_id' => $toUserId]);
        }
    }
};
