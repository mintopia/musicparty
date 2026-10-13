<?php

namespace App\Mods\Contracts;

use App\Models\Party;
use App\Models\UpcomingSong;

interface ModifiesScore
{
    public function modifyScore(Party $party, UpcomingSong $request, int $score): int;
}
