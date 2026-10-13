<?php

namespace App\Mods\Whamageddon;

use App\Enums\ScheduleFrequency;
use App\Enums\SettingType;
use App\Models\Party;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Mods\Contracts\DecoratesTracks;
use App\Mods\Contracts\Mod;
use App\Mods\ScheduledAction;
use App\Mods\SettingDefinition;
use App\Mods\TrackDecoration;
use App\Services\ModDataStore;
use Illuminate\Support\Facades\Log;

class WhamageddonMod implements DecoratesTracks, Mod
{
    public const CODE = 'whamageddon';

    public function code(): string
    {
        return self::CODE;
    }

    public function name(): string
    {
        return 'Whamageddon';
    }

    public function description(): string
    {
        return 'Last Christmas will be added to the queue. Every hour except between 2AM and 10AM, the Score will be bumped. Will the party be able to avoid it?';
    }

    public function settings(): array
    {
        return [
            new SettingDefinition('track', 'Track', SettingType::stTrack, '2FRnf9qhLbvw8fu4IBXx78'),
            new SettingDefinition('score_per_hour', 'Score per Hour', SettingType::stInteger, 1),
            new SettingDefinition('fallback_name', 'Fallback Name Override', SettingType::stString, 'Whamageddon'),
            new SettingDefinition('css_classes', 'CSS Classes', SettingType::stString, 'whamageddon'),
            new SettingDefinition('upcoming_song_id', 'Upcoming Song ID', SettingType::stInteger, private: true),
        ];
    }

    public function extensionPoints(): array
    {
        return ['scheduled-actions', 'queue-additions', 'decorations'];
    }

    public function decorate(Party $party, Song $song, ?UpcomingSong $request): ?TrackDecoration
    {
        if ($request === null) {
            return null;
        }
        $data = app(ModDataStore::class)->all($request, self::CODE);
        $displayName = $data['fallback_override'] ?? null;
        $cssClass = $data['css_classes'] ?? null;
        if ($displayName === null && $cssClass === null) {
            return null;
        }

        return new TrackDecoration(displayName: $displayName, cssClass: $cssClass);
    }

    public function scheduledActions(): array
    {
        return [
            new ScheduledAction('hourly', ScheduleFrequency::Hour, function (Party $party): void {
                Log::info("{$party} Whamageddon: Processing hourly tick");
                app(Whamageddon::class, ['party' => $party])->hourly();
            }),
        ];
    }

    public function onEnable(Party $party): void
    {
        app(Whamageddon::class, ['party' => $party])->enable();
    }

    public function onDisable(Party $party): void
    {
        app(Whamageddon::class, ['party' => $party])->disable();
    }
}
