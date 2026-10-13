<?php

use App\Models\Mod;
use App\Models\ModSetting;
use App\Models\Party;
use App\Mods\ModRegistry;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\FooModFixture;
use Tests\Support\WhamageddonFixtures;

it('writes mod and setting rows from a manifest', function () {
    FooModFixture::bindRegistry();

    Artisan::call('mods:sync');

    $mod = Mod::whereCode('foo')->firstOrFail();
    expect($mod->name)->toBe('Foo')->and($mod->description)->toBe('A fixture mod')->and($mod->removed_at)->toBeNull();
    $greeting = $mod->settings()->whereCode('greeting')->firstOrFail();
    $secret = $mod->settings()->whereCode('secret')->firstOrFail();
    expect($greeting->default)->toBe('hello')->and((bool) $greeting->private)->toBeFalse()
        ->and((bool) $secret->private)->toBeTrue();
});

it('is idempotent', function () {
    FooModFixture::bindRegistry();

    Artisan::call('mods:sync');
    Artisan::call('mods:sync');

    expect(Mod::count())->toBe(1)->and(ModSetting::count())->toBe(2);
});

it('marks a mod whose directory is gone as removed, clears party_mods and keeps setting rows', function () {
    WhamageddonFixtures::install();
    $party = WhamageddonFixtures::party(['enabled' => true, 'css_classes' => 'kept']);
    $settingRows = ModSetting::count();
    FooModFixture::bindRegistry();

    Artisan::call('mods:sync');

    $mod = Mod::whereCode('whamageddon')->firstOrFail();
    expect($mod->removed_at)->not->toBeNull()
        ->and($party->mods()->count())->toBe(0)
        ->and(ModSetting::whereMod_id($mod->id)->count())->toBe(5)
        ->and(ModSetting::count())->toBe($settingRows + 2)
        ->and($party->modsettings()->count())->toBe(1)
        ->and(Mod::whereCode('foo')->firstOrFail()->removed_at)->toBeNull();
});

it('keeps the original removed_at on repeated syncs', function () {
    WhamageddonFixtures::install();
    FooModFixture::bindRegistry();
    Artisan::call('mods:sync');
    $first = Mod::whereCode('whamageddon')->firstOrFail()->removed_at;

    $this->travel(2)->hours();
    Artisan::call('mods:sync');

    expect(Mod::whereCode('whamageddon')->firstOrFail()->removed_at)->toEqual($first);
});

it('clears removed_at when the mod reappears', function () {
    Mod::factory()->create(['code' => 'whamageddon', 'removed_at' => now()]);
    $registry = new ModRegistry(app_path('Mods'));
    $registry->discover();
    app()->instance(ModRegistry::class, $registry);

    Artisan::call('mods:sync');

    expect(Mod::whereCode('whamageddon')->count())->toBe(1)
        ->and(Mod::whereCode('whamageddon')->firstOrFail()->removed_at)->toBeNull();
});

it('does not touch parties that never enabled the mod', function () {
    WhamageddonFixtures::install();
    $party = Party::factory()->create();

    Artisan::call('mods:sync');

    expect($party->mods()->count())->toBe(0);
});
