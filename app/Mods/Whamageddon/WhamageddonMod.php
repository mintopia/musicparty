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
        return 'Last Christmas will be added to the queue. Every hour between 10AM and 1AM, 1 upvote will be added. Will the party be able to avoid it?';
    }

    public function settings(): array
    {
        return [
            new SettingDefinition('spotify_id', 'Track', SettingType::stTrack, '2FRnf9qhLbvw8fu4IBXx78'),
            new SettingDefinition('votes', 'Votes per Hour', SettingType::stInteger, 1),
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
                (new Whamageddon($party))->hourly();
            }),
        ];
    }

    public function onEnable(Party $party): void
    {
        (new Whamageddon($party))->enable();
    }

    public function onDisable(Party $party): void
    {
        (new Whamageddon($party))->disable();
    }
}
