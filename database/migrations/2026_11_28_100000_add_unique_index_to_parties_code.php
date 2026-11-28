<?php

use App\Services\PartyCodeGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $generator = app(PartyCodeGenerator::class);

        $duplicateCodes = DB::table('parties')
            ->select('code')
            ->groupBy('code')
            ->havingRaw('count(*) > 1')
            ->pluck('code');

        foreach ($duplicateCodes as $code) {
            $newer = DB::table('parties')->where('code', $code)->orderBy('id')->offset(1)->limit(PHP_INT_MAX)->pluck('id');

            foreach ($newer as $id) {
                $replacement = $generator->unused();
                DB::table('parties')->where('id', $id)->update(['code' => $replacement]);
                Log::warning('Deduplicated Party code', ['party_id' => $id, 'old_code' => $code, 'new_code' => $replacement]);
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
};
