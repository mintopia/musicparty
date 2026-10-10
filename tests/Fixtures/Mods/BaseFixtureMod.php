<?php

namespace Tests\Fixtures\Mods;

use App\Domain\Mod\Contracts\Mod;
use App\Domain\Mod\Data\SettingDefinition;
use App\Domain\Mod\SettingKind;

abstract class BaseFixtureMod implements Mod
{
    public function __construct(protected readonly string $id) {}

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return ucfirst($this->id).' Mod';
    }

    public function description(): string
    {
        return 'Test fixture.';
    }

    public function settings(): array
    {
        return [new SettingDefinition('failure_behaviour', 'On failure', SettingKind::Choice, 'accept', options: ['accept', 'hold'])];
    }

    public function requestRules(): array
    {
        return [];
    }

    public function scoreModifiers(): array
    {
        return [];
    }

    public function decorationProviders(): array
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
