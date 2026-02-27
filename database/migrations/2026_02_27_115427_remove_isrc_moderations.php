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
        DB::delete("DELETE FROM party_moderations WHERE type = 'mtISRC'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
