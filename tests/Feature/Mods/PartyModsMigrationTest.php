<?php

use App\Models\Mod;
use App\Models\ModSetting;
use App\Models\Party;
use Illuminate\Support\Facades\DB;

it('turns truthy legacy enabled settings into party_mods rows and drops the setting', function () {
    $migration = require database_path('migrations/2026_10_13_180000_add_per_party_mod_enablement.php');
    $migration->down();

    $mod = Mod::factory()->create(['code' => 'whamageddon']);
    $enabled = ModSetting::factory()->create(['mod_id' => $mod->id, 'code' => 'enabled']);
    $other = ModSetting::factory()->create(['mod_id' => $mod->id, 'code' => 'css_classes']);
    [$on, $off, $nullValue, $unset, $otherOnly] = Party::factory()->count(5)->create()->all();
    $row = fn (Party $party, ModSetting $setting, ?string $value) => DB::table('party_mod_settings')->insert([
        'party_id' => $party->id, 'mod_setting_id' => $setting->id, 'value' => $value,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $row($on, $enabled, serialize(true));
    $row($off, $enabled, serialize(false));
    $row($nullValue, $enabled, null);
    $row($otherOnly, $other, serialize('x'));

    $migration->up();

    expect(DB::table('party_mods')->pluck('party_id')->all())->toBe([$on->id])
        ->and(DB::table('party_mods')->where('party_id', $on->id)->value('mod_id'))->toBe($mod->id)
        ->and(ModSetting::whereCode('enabled')->exists())->toBeFalse()
        ->and(ModSetting::whereCode('css_classes')->exists())->toBeTrue();
});
