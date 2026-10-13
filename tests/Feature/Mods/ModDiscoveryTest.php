<?php

use App\Console\Commands\SyncModsCommand;
use App\Mods\Foo\FooMod;
use App\Mods\Foo\FooServiceProvider;
use App\Mods\ModRegistry;
use App\Mods\Whamageddon\WhamageddonMod;
use App\Mods\Whamageddon\WhamageddonServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\FooModFixture;

it('discovers the real mods and their providers in app/Mods', function () {
    $registry = app(ModRegistry::class);

    expect(array_keys($registry->all()))->toContain('whamageddon')
        ->and($registry->find('whamageddon'))->toBeInstanceOf(WhamageddonMod::class)
        ->and($registry->providers())->toContain(WhamageddonServiceProvider::class)
        ->and($registry->find('nope'))->toBeNull();
});

it('discovers a new mod directory with a manifest and provider and no other code change', function () {
    FooModFixture::bindRegistry();
    $registry = app(ModRegistry::class);

    expect($registry->find('foo'))->toBeInstanceOf(FooMod::class)
        ->and($registry->providers())->toBe([FooServiceProvider::class]);

    app()->register(FooServiceProvider::class);
    expect(app('foo-mod-registered'))->toBeTrue();
});

it('ignores directories without a manifest', function () {
    $dir = sys_get_temp_dir().'/mods-'.uniqid();
    mkdir($dir.'/Empty', recursive: true);

    $registry = new ModRegistry($dir);
    $registry->discover();
    rmdir($dir.'/Empty');
    rmdir($dir);

    expect($registry->all())->toBe([])->and($registry->providers())->toBe([]);
});

it('does not list any mod in bootstrap/providers.php or config/app.php', function () {
    $providers = file_get_contents(base_path('bootstrap/providers.php'));
    $config = file_get_contents(config_path('app.php'));

    expect($providers)->not->toContain('Whamageddon')->not->toContain('App\\Mods\\Whamageddon')
        ->and($config)->not->toContain('Whamageddon')
        ->and($providers)->toContain('ModServiceProvider');
});

it('registers the discovered mod providers through ModServiceProvider', function () {
    expect(array_keys(app()->getLoadedProviders()))->toContain(WhamageddonServiceProvider::class);
});

it('has removed the old install command and provider', function () {
    expect(file_exists(app_path('Mods/Whamageddon/Commands/InstallCommand.php')))->toBeFalse()
        ->and(file_exists(app_path('Mods/Whamageddon/WhammageddonServiceProvider.php')))->toBeFalse()
        ->and(class_exists(SyncModsCommand::class))->toBeTrue()
        ->and(array_key_exists('mods:whamageddon:install', Artisan::all()))->toBeFalse()
        ->and(array_key_exists('mods:sync', Artisan::all()))->toBeTrue();
});
