<?php

use App\Models\Party;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('mod_data')
            ->where('mod_code', 'trust')
            ->where('owner_type', (new Party)->getMorphClass())
            ->where('key', 'trustscore')
            ->delete();
    }

    public function down(): void {}
};
