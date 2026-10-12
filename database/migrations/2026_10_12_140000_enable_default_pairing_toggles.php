<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private const array TOGGLES = [
        'music_provider.spotify.enabled' => 'Spotify enabled',
        'music_provider.fake.enabled' => 'Fake (development) enabled',
        'player.polling.enabled' => 'Polling player enabled',
        'player.browser.enabled' => 'Browser player enabled',
        'player.soloist.enabled' => 'Soloist player enabled',
    ];

    public function up(): void
    {
        $existing = DB::table('settings')->whereIn('code', array_keys(self::TOGGLES))->pluck('code')->all();
        $order = (int) DB::table('settings')->max('order');

        foreach (self::TOGGLES as $code => $name) {
            if (in_array($code, $existing, true)) {
                continue;
            }

            DB::table('settings')->insert([
                'code' => $code,
                'name' => $name,
                'encrypted' => false,
                'hidden' => true,
                'value' => serialize(true),
                'validation' => '',
                'type' => 'stBoolean',
                'order' => ++$order,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('code', array_keys(self::TOGGLES))->delete();
    }
};
