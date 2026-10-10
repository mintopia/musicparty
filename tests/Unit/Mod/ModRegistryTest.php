<?php

use App\Domain\Mod\ModRegistry;
use Tests\Fixtures\Mods\SettingsFixtureMod;

it('registers and finds mods', function () {
    $registry = new ModRegistry;
    $mod = new SettingsFixtureMod;
    $registry->register($mod);

    expect($registry->find('settings-fixture'))->toBe($mod)
        ->and($registry->all())->toBe(['settings-fixture' => $mod])
        ->and($registry->find('nope'))->toBeNull();
});

it('throws on a duplicate id', function () {
    $registry = new ModRegistry;
    $registry->register(new SettingsFixtureMod);

    $registry->register(new SettingsFixtureMod);
})->throws(InvalidArgumentException::class);
