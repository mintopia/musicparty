<?php

namespace Tests\Fixtures\Architecture;

use App\Models\Play;

class CrossContextWriter
{
    public function write(): void
    {
        Play::create(['title' => 'x']);
    }
}
