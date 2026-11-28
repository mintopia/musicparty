<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateCodes = DB::table('parties')
            ->select('code')
            ->groupBy('code')
            ->havingRaw('count(*) > 1')
            ->pluck('code');

        foreach ($duplicateCodes as $code) {
            $newer = DB::table('parties')->where('code', $code)->orderBy('id')->offset(1)->limit(PHP_INT_MAX)->pluck('id');

            foreach ($newer as $id) {
                $replacement = $this->unusedCode();
                DB::table('parties')->where('id', $id)->update(['code' => $replacement]);
                Log::warning('Deduplicated Party code', ['party_id' => $id, 'old_code' => $code, 'new_code' => $replacement]);
                echo "Party {$id}: code {$code} renamed to {$replacement}".PHP_EOL;
            }
        }

        Schema::table('parties', function (Blueprint $table) {
            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->dropUnique(['code']);
        });
    }

    private function unusedCode(): string
    {
        do {
            $code = Str::upper(Str::password(4, numbers: false, symbols: false));
        } while (DB::table('parties')->where('code', $code)->exists());

        return $code;
    }
};
