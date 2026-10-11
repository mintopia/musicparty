<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_audit_entries', function (Blueprint $table): void {
            $table->string('admin_name')->nullable()->after('admin_id');
            $table->string('subject_name')->nullable()->after('subject_user_id');
        });

        DB::table('admin_audit_entries')->orderBy('id')->each(function (object $entry): void {
            $adminName = DB::table('users')->where('id', $entry->admin_id)->value('nickname');
            $subjectName = $entry->subject_user_id === null
                ? null
                : DB::table('users')->where('id', $entry->subject_user_id)->value('nickname');

            DB::table('admin_audit_entries')->where('id', $entry->id)->update([
                'admin_name' => $adminName ?? 'Unknown',
                'subject_name' => $subjectName,
            ]);
        });

        Schema::table('admin_audit_entries', function (Blueprint $table): void {
            $table->dropForeign(['admin_id']);
        });

        Schema::table('admin_audit_entries', function (Blueprint $table): void {
            $table->string('admin_name')->nullable(false)->change();
            $table->unsignedBigInteger('admin_id')->nullable()->change();
        });

        Schema::table('admin_audit_entries', function (Blueprint $table): void {
            $table->foreign('admin_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('admin_audit_entries', function (Blueprint $table): void {
            $table->dropForeign(['admin_id']);
        });

        DB::table('admin_audit_entries')->whereNull('admin_id')->delete();

        Schema::table('admin_audit_entries', function (Blueprint $table): void {
            $table->unsignedBigInteger('admin_id')->nullable(false)->change();
        });

        Schema::table('admin_audit_entries', function (Blueprint $table): void {
            $table->foreign('admin_id')->references('id')->on('users')->cascadeOnDelete();
            $table->dropColumn(['admin_name', 'subject_name']);
        });
    }
};
