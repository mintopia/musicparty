<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->hasSocialProviderForeignKey()) {
            return;
        }

        DB::table('linked_accounts')
            ->whereNotNull('social_provider_id')
            ->whereNotIn('social_provider_id', DB::table('social_providers')->select('id'))
            ->update(['social_provider_id' => null]);

        Schema::table('linked_accounts', function (Blueprint $table) {
            $table->foreign('social_provider_id')->references('id')->on('social_providers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (! $this->hasSocialProviderForeignKey()) {
            return;
        }

        Schema::table('linked_accounts', function (Blueprint $table) {
            $table->dropForeign(['social_provider_id']);
        });
    }

    private function hasSocialProviderForeignKey(): bool
    {
        foreach (Schema::getForeignKeys('linked_accounts') as $foreignKey) {
            if ($foreignKey['columns'] === ['social_provider_id']) {
                return true;
            }
        }

        return false;
    }
};
