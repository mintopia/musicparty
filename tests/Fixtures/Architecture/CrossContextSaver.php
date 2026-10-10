<?php

namespace Tests\Fixtures\Architecture;

use App\Domain\Party\Models\Party;

class CrossContextSaver
{
    public function write(Party $party): void
    {
        $party->save();
    }
}
