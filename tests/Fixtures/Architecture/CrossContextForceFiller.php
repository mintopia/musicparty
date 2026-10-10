<?php

namespace Tests\Fixtures\Architecture;

use App\Domain\Party\Models\Party;

class CrossContextForceFiller
{
    public function write(Party $party): void
    {
        $party->forceFill(['name' => 'x'])->save();
    }
}
