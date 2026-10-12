<?php

use App\Enums\SettingType;
use App\Models\Mod;
use App\Models\ModSetting;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\WhamageddonFixtures;

it('creates the whamageddon mod with its settings and defaults', function () {
    expect(Artisan::call('mods:whamageddon:install'))->toBe(0);

    $mod = Mod::whereCode('whamageddon')->firstOrFail();
    expect($mod->name)->toBe('Whamageddon');

    $expected = [
        'enabled' => [SettingType::stBoolean, true, false],
        'spotify_id' => [SettingType::stString, '2FRnf9qhLbvw8fu4IBXx78', false],
        'votes' => [SettingType::stInteger, 1, false],
        'fallback_name' => [SettingType::stString, 'Whamageddon', false],
        'css_classes' => [SettingType::stString, 'whamageddon', false],
        'upcoming_song_id' => [SettingType::stInteger, 0, true],
    ];
    expect($mod->settings()->count())->toBe(count($expected));
    foreach ($expected as $code => [$type, $default, $private]) {
        $setting = $mod->settings()->whereCode($code)->firstOrFail();
        expect($setting->type)->toBe($type)
            ->and($setting->default)->toBe($default)
            ->and((bool) $setting->private)->toBe($private)
            ->and((bool) $setting->encrypted)->toBeFalse();
    }
});

it('pins that a false boolean default reads back as true through the SettingValue cast', function () {
    WhamageddonFixtures::install();

    $setting = ModSetting::whereCode('enabled')->firstOrFail();
    expect($setting->getRawOriginal('default'))->toBe('b:0;')
        ->and($setting->default)->toBeTrue();
});

it('does not define a score_adjustment setting even though the mod reads one', function () {
    WhamageddonFixtures::install();

    expect(ModSetting::whereCode('score_adjustment')->exists())->toBeFalse()
        ->and(ModSetting::whereCode('votes')->exists())->toBeTrue();
});

it('is idempotent and restores edited names and defaults on re-run', function () {
    WhamageddonFixtures::install();
    Mod::whereCode('whamageddon')->update(['name' => 'Edited']);
    $setting = ModSetting::whereCode('fallback_name')->firstOrFail();
    $setting->name = 'Edited Name';
    $setting->default = 'Edited Default';
    $setting->save();

    WhamageddonFixtures::install();

    expect(Mod::whereCode('whamageddon')->count())->toBe(1)
        ->and(Mod::whereCode('whamageddon')->first()->name)->toBe('Whamageddon')
        ->and(ModSetting::count())->toBe(6);
    $setting->refresh();
    expect($setting->name)->toBe('Fallback Name Override')
        ->and($setting->default)->toBe('Whamageddon');
});
