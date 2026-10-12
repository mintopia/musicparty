<?php

namespace App\Domain\Mod\Trust;

use App\Domain\Mod\Contracts\Mod;
use App\Domain\Mod\Data\SettingDefinition;
use App\Domain\Mod\SettingKind;

class TrustScoreMod implements Mod
{
    public const string ID = 'trust-score';

    public function __construct(private readonly TrustScheduledAction $scheduledAction) {}

    public function id(): string
    {
        return self::ID;
    }

    public function name(): string
    {
        return 'Trust Score';
    }

    public function description(): string
    {
        return 'Ranks Members by how much the Host and the Members the Host trusts upvote their Requests, shows the score to staff, and can add a Score bonus for trusted requesters.';
    }

    public function settings(): array
    {
        return [
            new SettingDefinition('anchor_member_id', 'Anchor Member id (0 = the Host)', SettingKind::Integer, 0, min: 0),
            new SettingDefinition('window_days', 'Days of Votes and Ratings to count', SettingKind::Integer, 7, min: 1, max: 365),
            new SettingDefinition('weighting', 'Add a Score bonus for trusted requesters', SettingKind::Boolean, false),
            new SettingDefinition('max_bonus', 'Maximum Score bonus', SettingKind::Integer, 2, min: 0, max: 10),
        ];
    }

    public function requestRules(): array
    {
        return [];
    }

    public function scoreModifiers(): array
    {
        return [new TrustScoreModifier];
    }

    public function decorationProviders(): array
    {
        return [];
    }

    public function scheduledActions(): array
    {
        return [$this->scheduledAction];
    }

    public function listeners(): array
    {
        return [];
    }
}
