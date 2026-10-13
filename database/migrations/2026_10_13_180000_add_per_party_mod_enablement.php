<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mods', function (Blueprint $table): void {
            $table->timestamp('removed_at')->nullable();
            $table->unique('code');
        });

        Schema::table('mod_settings', function (Blueprint $table): void {
            $table->unique(['mod_id', 'code']);
        });

        Schema::create('party_mods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mod_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['party_id', 'mod_id']);
        });

        $legacy = DB::table('party_mod_settings')
            ->join('mod_settings', 'mod_settings.id', '=', 'party_mod_settings.mod_setting_id')
            ->where('mod_settings.code', 'enabled')
            ->select(['party_mod_settings.party_id', 'mod_settings.mod_id', 'party_mod_settings.value'])
            ->get();

        foreach ($legacy as $row) {
            $value = $row->value === null ? false : @unserialize($row->value);
            if ($value) {
                DB::table('party_mods')->insertOrIgnore([
                    'party_id' => $row->party_id,
                    'mod_id' => $row->mod_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('mod_settings')->where('code', 'enabled')->delete();
    }

    public function down(): void
    {
        Schema::dropIfExists('party_mods');

        Schema::table('mod_settings', function (Blueprint $table): void {
            $table->dropUnique(['mod_id', 'code']);
        });

        Schema::table('mods', function (Blueprint $table): void {
            $table->dropUnique(['code']);
            $table->dropColumn('removed_at');
        });
    }
};
