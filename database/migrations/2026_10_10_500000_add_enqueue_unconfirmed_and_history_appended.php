<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('track_requests', function (Blueprint $table) {
            $table->boolean('enqueue_unconfirmed')->default(false);
        });

        Schema::table('plays', function (Blueprint $table) {
            $table->timestamp('history_appended_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('track_requests', function (Blueprint $table) {
            $table->dropColumn('enqueue_unconfirmed');
        });

        Schema::table('plays', function (Blueprint $table) {
            $table->dropColumn('history_appended_at');
        });
    }
};
