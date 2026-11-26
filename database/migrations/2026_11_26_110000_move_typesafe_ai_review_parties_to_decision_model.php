<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const string MOD = 'ai-request-review';

    private const string ENDPOINT = 'https://api.typesafe.ai/v1/systemone';

    private const string MODEL = 'jev-latest';

    public function up(): void
    {
        $modId = DB::table('mods')->where('code', self::MOD)->value('id');
        if ($modId === null) {
            return;
        }
        $settings = DB::table('mod_settings')->where('mod_id', $modId)->pluck('id', 'code');
        if (! isset($settings['driver'])) {
            return;
        }

        $drivers = DB::table('party_mod_settings')->where('mod_setting_id', $settings['driver'])->pluck('value', 'party_id');
        $enabled = DB::table('party_mods')->where('mod_id', $modId)->pluck('party_id');
        foreach ($enabled->merge($drivers->keys())->unique() as $partyId) {
            $driver = isset($drivers[$partyId]) ? $this->read($drivers[$partyId]) : 'typesafe';
            if ($driver !== 'typesafe') {
                continue;
            }
            $model = isset($settings['model'])
                ? $this->read(DB::table('party_mod_settings')->where('party_id', $partyId)->where('mod_setting_id', $settings['model'])->value('value'))
                : null;
            $this->write($partyId, $settings['driver'], 'decision-model');
            if (isset($settings['decision_endpoint'])) {
                $this->write($partyId, $settings['decision_endpoint'], self::ENDPOINT);
            }
            if (isset($settings['decision_model'])) {
                $this->write($partyId, $settings['decision_model'], is_string($model) && trim($model) !== '' ? trim($model) : self::MODEL);
            }
        }
    }

    private function read(mixed $stored): mixed
    {
        if (! is_string($stored)) {
            return null;
        }
        $value = @unserialize($stored);

        return $value === false && $stored !== 'b:0;' ? $stored : $value;
    }

    private function write(int|string $partyId, int|string $settingId, string $value): void
    {
        $key = ['party_id' => $partyId, 'mod_setting_id' => $settingId];
        $row = DB::table('party_mod_settings')->where($key)->first();
        if ($row === null) {
            DB::table('party_mod_settings')->insert($key + ['value' => serialize($value), 'created_at' => now(), 'updated_at' => now()]);

            return;
        }
        DB::table('party_mod_settings')->where('id', $row->id)->update(['value' => serialize($value), 'updated_at' => now()]);
    }
};
