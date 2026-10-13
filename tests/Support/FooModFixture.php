<?php

namespace Tests\Support;

use App\Mods\Foo\FooMod;
use App\Mods\ModRegistry;

class FooModFixture
{
    public const PATH = __DIR__.'/../Fixtures/Mods';

    /** Bind a registry that discovers only the Foo fixture mod. */
    public static function bindRegistry(): ModRegistry
    {
        require_once self::PATH.'/Foo/FooMod.php';
        require_once self::PATH.'/Foo/FooServiceProvider.php';
        FooMod::$calls = [];

        $registry = new ModRegistry(self::PATH);
        $registry->discover();
        app()->instance(ModRegistry::class, $registry);

        return $registry;
    }
}
