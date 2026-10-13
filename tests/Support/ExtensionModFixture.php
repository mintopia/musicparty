<?php

namespace Tests\Support;

use App\Mods\Bar\BarMod;
use App\Mods\ModRegistry;

class ExtensionModFixture
{
    public const PATH = __DIR__.'/../Fixtures/ExtensionMods';

    public static function bindRegistry(): ModRegistry
    {
        require_once self::PATH.'/Bar/BarMod.php';
        require_once self::PATH.'/Boom/BoomMod.php';
        BarMod::$bonuses = [];

        $registry = new ModRegistry(self::PATH);
        $registry->discover();
        app()->instance(ModRegistry::class, $registry);

        return $registry;
    }
}
