<?php

namespace Tests\Fixtures\Architecture;

use App\Models\Song;

class CrossContextWriter
{
    public function write(): void
    {
        Song::create(['name' => 'x']);
    }
}
