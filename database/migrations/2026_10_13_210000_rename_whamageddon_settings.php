<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->rename('spotify_id', 'track');
        $this->rename('votes', 'score_per_hour');
    }

    public function down(): void
    {
        $this->rename('track', 'spotify_id');
        $this->rename('score_per_hour', 'votes');
    }

    private function rename(string $from, string $to): void
    {
        $modId = DB::table('mods')->where('code', 'whamageddon')->value('id');
        if ($modId === null) {
            return;
        }
        $old = DB::table('mod_settings')->where('mod_id', $modId)->where('code', $from)->first();
        if ($old === null) {
            return;
        }
        $new = DB::table('mod_settings')->where('mod_id', $modId)->where('code', $to)->first();
        if ($new === null) {
            DB::table('mod_settings')->where('id', $old->id)->update(['code' => $to]);

            return;
        }
        $taken = DB::table('party_mod_settings')->where('mod_setting_id', $new->id)->pluck('party_id');
        DB::table('party_mod_settings')->where('mod_setting_id', $old->id)->whereNotIn('party_id', $taken)->update(['mod_setting_id' => $new->id]);
        DB::table('mod_settings')->where('id', $old->id)->delete();
    }
};
