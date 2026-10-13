<?php

namespace App\Mods\Contracts;

use App\Models\Party;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Mods\TrackDecoration;

interface DecoratesTracks
{
    public function decorate(Party $party, Song $song, ?UpcomingSong $request): ?TrackDecoration;
}
