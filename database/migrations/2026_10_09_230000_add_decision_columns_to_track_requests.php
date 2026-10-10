<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('track_requests', function (Blueprint $table) {
            $table->foreignId('decided_by_member_id')->nullable()->constrained('party_members')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('rejection_reason', 200)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('track_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('decided_by_member_id');
            $table->dropColumn(['decided_at', 'rejection_reason']);
        });
    }
};
