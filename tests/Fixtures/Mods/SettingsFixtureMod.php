<?php

namespace Tests\Fixtures\Mods;

use App\Domain\Mod\Contracts\Mod;
use App\Domain\Mod\Data\SettingDefinition;
use App\Domain\Mod\SettingKind;

class SettingsFixtureMod implements Mod
{
    public function __construct(private readonly string $id = 'settings-fixture') {}

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return 'Settings Fixture';
    }

    public function description(): string
    {
        return 'Exercises every setting kind.';
    }

    public function settings(): array
    {
        return [
            new SettingDefinition('active', 'Active', SettingKind::Boolean, true),
            new SettingDefinition('limit', 'Limit', SettingKind::Integer, 5, min: 1, max: 10),
            new SettingDefinition('note', 'Note', SettingKind::Text, 'hello'),
            new SettingDefinition('mode', 'Mode', SettingKind::Choice, 'a', options: ['a', 'b']),
            new SettingDefinition('token', 'Token', SettingKind::Secret),
        ];
    }

    public function requestRules(): array
    {
        return [];
    }

    public function scoreModifiers(): array
    {
        return [];
    }

    public function scheduledActions(): array
    {
        return [];
    }

    public function listeners(): array
    {
        return [];
    }
}
