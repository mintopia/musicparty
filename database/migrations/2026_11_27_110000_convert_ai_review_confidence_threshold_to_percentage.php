<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const string MOD = 'ai-request-review';

    private const string OLD = 'confidence_threshold';

    private const string NEW = 'confidence_percentage';

    public function up(): void
    {
        $this->convert(self::OLD, self::NEW, fn (float $value): int => (int) round($value * 100));
    }

    public function down(): void
    {
        $this->convert(self::NEW, self::OLD, fn (float $value): float => round($value / 100, 2));
    }

    /**
     * @param  Closure(float): (int|float)  $transform
     */
    private function convert(string $from, string $to, Closure $transform): void
    {
        $modId = DB::table('mods')->where('code', self::MOD)->value('id');
        if ($modId === null) {
            return;
        }
        $source = DB::table('mod_settings')->where('mod_id', $modId)->where('code', $from)->first();
        if ($source === null) {
            return;
        }
        $target = DB::table('mod_settings')->where('mod_id', $modId)->where('code', $to)->first();

        if ($target === null) {
            DB::table('mod_settings')->where('id', $source->id)->update(['code' => $to]);
        }

        foreach (DB::table('party_mod_settings')->where('mod_setting_id', $source->id)->get() as $row) {
            $stored = $this->read($row->value);
            if (! is_numeric($stored)) {
                continue;
            }
            $update = ['value' => serialize($transform((float) $stored)), 'updated_at' => now()];
            if ($target === null) {
                DB::table('party_mod_settings')->where('id', $row->id)->update($update);
            } elseif (! DB::table('party_mod_settings')->where('party_id', $row->party_id)->where('mod_setting_id', $target->id)->exists()) {
                DB::table('party_mod_settings')->where('id', $row->id)->update($update + ['mod_setting_id' => $target->id]);
            }
        }

        if ($target !== null) {
            DB::table('party_mod_settings')->where('mod_setting_id', $source->id)->delete();
            DB::table('mod_settings')->where('id', $source->id)->delete();
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
};
