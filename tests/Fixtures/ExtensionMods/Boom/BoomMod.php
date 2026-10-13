<?php

namespace App\Mods\Boom;

use App\Models\Party;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Mods\Contracts\DecoratesTracks;
use App\Mods\Contracts\Mod;
use App\Mods\Contracts\ModifiesScore;
use App\Mods\TrackDecoration;
use RuntimeException;

class BoomMod implements DecoratesTracks, Mod, ModifiesScore
{
    public function code(): string
    {
        return 'boom';
    }

    public function name(): string
    {
        return 'Boom';
    }

    public function description(): string
    {
        return 'A fixture mod whose extensions always fail';
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
        throw new RuntimeException('boom');
    }

    public function decorate(Party $party, Song $song, ?UpcomingSong $request): ?TrackDecoration
    {
        throw new RuntimeException('boom');
    }

    public function scheduledActions(): array
    {
        return [];
    }

    public function onEnable(Party $party): void {}

    public function onDisable(Party $party): void {}
}
