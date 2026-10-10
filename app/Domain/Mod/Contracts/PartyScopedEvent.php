<?php

namespace App\Domain\Mod\Contracts;

use App\Models\Party;

interface PartyScopedEvent
{
    public function party(): Party;
}
