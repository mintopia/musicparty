<?php

namespace App\Mods\Foo;

use App\Enums\SettingType;
use App\Mods\Contracts\Mod;
use App\Mods\SettingDefinition;
use App\Models\Party;

class FooMod implements Mod
{
    /** @var list<string> */
    public static array $calls = [];

    public function code(): string
    {
        return 'foo';
    }

    public function name(): string
    {
        return 'Foo';
    }

    public function description(): string
    {
        return 'A fixture mod';
    }

    public function settings(): array
    {
        return [
            new SettingDefinition('greeting', 'Greeting', SettingType::stString, 'hello'),
            new SettingDefinition('secret', 'Secret', SettingType::stInteger, private: true),
        ];
    }

    public function extensionPoints(): array
    {
        return [];
    }

    public function onEnable(Party $party): void
    {
        self::$calls[] = "enable:{$party->id}";
    }

    public function onDisable(Party $party): void
    {
        self::$calls[] = "disable:{$party->id}";
    }
}
