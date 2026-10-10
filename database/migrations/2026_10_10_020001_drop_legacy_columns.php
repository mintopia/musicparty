<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PARTY_COLUMNS = [
        'song_started_at', 'recent_device_id', 'device_id', 'device_name', 'queue', 'force', 'poll',
        'last_updated_at', 'weighted', 'trustscore', 'trusted_user_id', 'show_qrcode', 'active',
        'backup_playlist_id', 'backup_playlist_name',
    ];

    private const USER_COLUMNS = ['status', 'status_updated_at', 'market'];

    public function up(): void
    {
        if (Schema::hasColumn('parties', 'trusted_user_id')) {
            Schema::table('parties', function (Blueprint $table) {
                $table->dropForeign(['trusted_user_id']);
            });
        }

        $this->dropExisting('parties', self::PARTY_COLUMNS);
        $this->dropExisting('party_members', ['trustscore']);
        $this->dropExisting('users', self::USER_COLUMNS);
    }

    public function down(): void {}

    /**
     * @param  list<string>  $columns
     */
    private function dropExisting(string $table, array $columns): void
    {
        $existing = array_values(array_filter($columns, fn (string $column): bool => Schema::hasColumn($table, $column)));

        if ($existing === []) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($existing) {
            $blueprint->dropColumn($existing);
        });
    }
};
