<?php

namespace App\Mods\Bar;

use App\Models\Party;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Mods\Contracts\DecoratesTracks;
use App\Mods\Contracts\Mod;
use App\Mods\Contracts\ModifiesScore;
use App\Mods\TrackDecoration;

class BarMod implements DecoratesTracks, Mod, ModifiesScore
{
    /** @var array<string, int> */
    public static array $bonuses = [];

    public function code(): string
    {
        return 'bar';
    }

    public function name(): string
    {
        return 'Bar';
    }

    public function description(): string
    {
        return 'An extension fixture mod';
    }

    public function settings(): array
    {
        return [];
    }

    public function extensionPoints(): array
    {
        return ['score-modifiers', 'decorations'];
    }

    public function modifyScore(Party $party, UpcomingSong $request, int $score): int
    {
        return $score + (self::$bonuses[$request->song->provider_track_id] ?? 0);
    }

    public function decorate(Party $party, Song $song, ?UpcomingSong $request): ?TrackDecoration
    {
        return new TrackDecoration(displayName: "Bar {$song->provider_track_id}", label: 'bar');
    }

    public function scheduledActions(): array
    {
        return [];
    }

    public function onEnable(Party $party): void {}

    public function onDisable(Party $party): void {}
}
