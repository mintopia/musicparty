<?php

namespace App\Domain\Mod\Contracts;

use App\Domain\Party\Models\Party;

interface PartyScopedEvent
{
    public function party(): Party;
}
