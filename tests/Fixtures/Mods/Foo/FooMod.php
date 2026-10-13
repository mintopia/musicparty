<?php

namespace App\Mods\Foo;

use App\Enums\SettingType;
use App\Models\Party;
use App\Mods\Contracts\Mod;
use App\Mods\ScheduledAction;
use App\Mods\SettingDefinition;

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

    /** @var list<ScheduledAction> */
    public static array $actions = [];

    public function scheduledActions(): array
    {
        return self::$actions;
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
