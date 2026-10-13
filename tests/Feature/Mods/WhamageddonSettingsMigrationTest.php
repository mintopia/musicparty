<?php

use App\Models\Mod;
use App\Models\ModSetting;
use App\Models\PartyModSetting;
use App\Services\ModSettingsStore;
use Tests\Support\WhamageddonFixtures;

function renameMigration(): object
{
    return require database_path('migrations/2026_10_13_210000_rename_whamageddon_settings.php');
}

function legacySettings(): void
{
    WhamageddonFixtures::install();
    $modId = Mod::whereCode('whamageddon')->value('id');
    ModSetting::whereMod_id($modId)->whereCode('track')->update(['code' => 'spotify_id']);
    ModSetting::whereMod_id($modId)->whereCode('score_per_hour')->update(['code' => 'votes']);
}

it('renames the legacy settings in place so existing party values survive', function () {
    legacySettings();
    $party = WhamageddonFixtures::party();
    $modId = Mod::whereCode('whamageddon')->value('id');
    $votes = ModSetting::whereMod_id($modId)->whereCode('votes')->firstOrFail();
    $track = ModSetting::whereMod_id($modId)->whereCode('spotify_id')->firstOrFail();
    PartyModSetting::query()->insert([
        ['party_id' => $party->id, 'mod_setting_id' => $votes->id, 'value' => serialize(4), 'created_at' => now(), 'updated_at' => now()],
        ['party_id' => $party->id, 'mod_setting_id' => $track->id, 'value' => serialize('abc123'), 'created_at' => now(), 'updated_at' => now()],
    ]);

    renameMigration()->up();
    WhamageddonFixtures::install();

    $store = app(ModSettingsStore::class);
    expect($store->value($party->fresh(), 'whamageddon', 'score_per_hour'))->toBe(4)
        ->and($store->value($party->fresh(), 'whamageddon', 'track'))->toBe('abc123')
        ->and(ModSetting::whereMod_id($modId)->whereIn('code', ['votes', 'spotify_id'])->exists())->toBeFalse();
});

it('moves party values onto the new definitions when mods:sync already created them', function () {
    legacySettings();
    WhamageddonFixtures::install();
    $party = WhamageddonFixtures::party();
    $modId = Mod::whereCode('whamageddon')->value('id');
    $votes = ModSetting::whereMod_id($modId)->whereCode('votes')->firstOrFail();
    PartyModSetting::query()->insert(['party_id' => $party->id, 'mod_setting_id' => $votes->id, 'value' => serialize(7), 'created_at' => now(), 'updated_at' => now()]);

    renameMigration()->up();

    expect(app(ModSettingsStore::class)->value($party->fresh(), 'whamageddon', 'score_per_hour'))->toBe(7)
        ->and(ModSetting::whereMod_id($modId)->whereCode('votes')->exists())->toBeFalse();
});
