<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('linked_accounts', function (Blueprint $table): void {
            $table->string('email_normalised', 191)->nullable()->index();
        });

        DB::table('linked_accounts')->whereNotNull('email')->orderBy('id')->each(function (object $row): void {
            $normalised = mb_strtolower(trim((string) $row->email));

            DB::table('linked_accounts')->where('id', $row->id)->update([
                'email_normalised' => $normalised === '' ? null : mb_substr($normalised, 0, 191),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('linked_accounts', function (Blueprint $table): void {
            $table->dropIndex(['email_normalised']);
            $table->dropColumn('email_normalised');
        });
    }
};
