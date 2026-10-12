<?php

namespace App\Domain\Mod\Whamageddon;

use App\Domain\Mod\Contracts\Mod;
use App\Domain\Mod\Data\ModContext;
use App\Domain\Mod\Data\SettingDefinition;
use App\Domain\Mod\SettingKind;
use App\Domain\Queue\Events\RequestCreated;

class WhamageddonMod implements Mod
{
    public const string ID = 'whamageddon';

    public function __construct(
        private readonly WhamageddonScheduledAction $scheduledAction,
        private readonly WhamageddonStateTracker $tracker,
    ) {}

    public function id(): string
    {
        return self::ID;
    }

    public function name(): string
    {
        return 'Whamageddon';
    }

    public function description(): string
    {
        return 'Keeps one Track in the Queue as a system Request that gains Score every hour until it plays, then returns after an interval, with overnight quiet hours.';
    }

    public function settings(): array
    {
        return [
            new SettingDefinition('track', 'Track (Spotify Track id or link)', SettingKind::Text, WhamageddonSettings::DEFAULT_TRACK),
            new SettingDefinition('label', 'Badge label', SettingKind::Text, 'Whamageddon'),
            new SettingDefinition('hourly_bonus', 'Score bonus added each hour', SettingKind::Integer, 1, min: 0, max: 100),
            new SettingDefinition('readd_interval_hours', 'Hours after it plays before it is added again', SettingKind::Integer, 2, min: 0, max: 168),
            new SettingDefinition('quiet_start_hour', 'Quiet hours start (hour of day, no bonus)', SettingKind::Integer, 2, min: 0, max: 23),
            new SettingDefinition('quiet_end_hour', 'Quiet hours end (hour of day)', SettingKind::Integer, 10, min: 0, max: 23),
            new SettingDefinition('hold_from_hour', 'Hold the Request back from this hour until quiet hours end', SettingKind::Integer, 23, min: 0, max: 23),
        ];
    }

    public function requestRules(): array
    {
        return [];
    }

    public function scoreModifiers(): array
    {
        return [new WhamageddonScoreModifier];
    }

    public function decorationProviders(): array
    {
        return [new WhamageddonDecorationProvider];
    }

    public function scheduledActions(): array
    {
        return [$this->scheduledAction];
    }

    public function listeners(): array
    {
        return [
            RequestCreated::class => function (object $event, ModContext $context): void {
                if ($event instanceof RequestCreated) {
                    $this->tracker->adoptSystemRequest($context, $event->request);
                }
            },
        ];
    }
}
