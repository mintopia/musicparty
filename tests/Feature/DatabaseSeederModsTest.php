<?php

use App\Models\Mod;
use App\Models\ModSetting;
use App\Mods\ModRegistry;
use Database\Seeders\DatabaseSeeder;

it('syncs registry mods and their settings when seeding', function () {
    $this->seed(DatabaseSeeder::class);

    $registered = count(app(ModRegistry::class)->all());

    expect($registered)->toBeGreaterThan(0)
        ->and(Mod::count())->toBe($registered)
        ->and(ModSetting::count())->toBeGreaterThan(0);
});
