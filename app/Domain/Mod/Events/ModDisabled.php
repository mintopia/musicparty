<?php

namespace App\Domain\Mod\Events;

use App\Domain\Party\Models\Party;
use Illuminate\Foundation\Events\Dispatchable;

class ModDisabled
{
    use Dispatchable;

    public function __construct(public Party $party, public string $modId) {}
}
