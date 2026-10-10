<?php

namespace App\Domain\Mod\Variety;

use App\Domain\Mod\Contracts\Mod;
use App\Domain\Mod\Data\SettingDefinition;
use App\Domain\Mod\SettingKind;

class VarietyMod implements Mod
{
    public const string ID = 'variety';

    public function id(): string
    {
        return self::ID;
    }

    public function name(): string
    {
        return 'Variety';
    }

    public function description(): string
    {
        return 'Down-weights Requests from the same requester or by the same artist as the currently playing Track, so a small voting block cannot dominate the Queue.';
    }

    public function settings(): array
    {
        return [
            new SettingDefinition('requester_penalty_percent', 'Score penalty for the same requester as the playing Track (%, 0 = off)', SettingKind::Integer, 50, min: 0, max: 100),
            new SettingDefinition('artist_penalty_percent', 'Score penalty for the same artist as the playing Track (%, 0 = off)', SettingKind::Integer, 50, min: 0, max: 100),
        ];
    }

    public function requestRules(): array
    {
        return [];
    }

    public function scoreModifiers(): array
    {
        return [new VarietyScoreModifier];
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
