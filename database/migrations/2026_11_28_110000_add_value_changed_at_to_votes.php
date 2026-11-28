<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('votes', function (Blueprint $table) {
            $table->timestamp('value_changed_at')->nullable()->after('value');
            $table->index(['user_id', 'value', 'value_changed_at']);
        });

        DB::table('votes')->update(['value_changed_at' => DB::raw('COALESCE(updated_at, created_at)')]);
    }

    public function down(): void
    {
        Schema::table('votes', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'value', 'value_changed_at']);
            $table->dropColumn('value_changed_at');
        });
    }
};
