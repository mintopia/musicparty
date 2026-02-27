<?php

use App\Models\PartyModeration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $moderations = PartyModeration::whereType('mtISRC')->get();
        foreach ($moderations as $moderation) {
            Log::info("{$moderation} - Deleted as ISRC is no-longer supported");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
