<?php

namespace App\Mods\Trust;

use App\Enums\ScheduleFrequency;
use App\Enums\SettingType;
use App\Models\Party;
use App\Models\UpcomingSong;
use App\Mods\Contracts\Mod;
use App\Mods\Contracts\ModifiesScore;
use App\Mods\ScheduledAction;
use App\Mods\SettingDefinition;
use App\Services\ModSettingsStore;

class TrustMod implements Mod, ModifiesScore
{
    public const CODE = 'trust';

    public function code(): string
    {
        return self::CODE;
    }

    public function name(): string
    {
        return 'Trust';
    }

    public function description(): string
    {
        return 'Builds a reputation for each member from the endorsements their requests receive and gives requests from trusted members a small score bonus, never blocking anyone.';
    }

    public function settings(): array
    {
        return [
            new SettingDefinition('trusted_user_id', 'Trusted User', SettingType::stInteger, null, 'ID of the user whose endorsements anchor the reputation. Defaults to the Party owner.', validation: ['exists:users,id']),
            new SettingDefinition('max_bonus', 'Maximum Score Bonus', SettingType::stInteger, 3, 'Score added to a request from a fully trusted member.', validation: ['min:0', 'max:10']),
            new SettingDefinition('window_days', 'Window (Days)', SettingType::stInteger, 7, 'How many days of votes and ratings count as endorsements.', validation: ['min:1', 'max:90']),
            new SettingDefinition('interval_minutes', 'Recalculation Interval (Minutes)', SettingType::stInteger, 15, 'Minimum time between trust recalculations.', validation: ['min:1', 'max:1440']),
        ];
    }

    public function extensionPoints(): array
    {
        return ['scheduled-actions', 'score-modifiers'];
    }

    public function scheduledActions(): array
    {
        return [
            new ScheduledAction('recalculate', ScheduleFrequency::Minute, function (Party $party): void {
                app(TrustScores::class)->recalculateIfDue($party);
            }),
        ];
    }

    public function modifyScore(Party $party, UpcomingSong $request, int $score): int
    {
        if ($request->user_id === null) {
            return $score;
        }
        $maxBonus = (int) app(ModSettingsStore::class)->value($party, self::CODE, 'max_bonus', 3);

        return $score + (int) round(app(TrustScores::class)->scoreFor($party, $request->user_id) * $maxBonus);
    }

    public function onEnable(Party $party): void {}

    public function onDisable(Party $party): void
    {
        app(TrustScores::class)->forget($party);
    }
}
