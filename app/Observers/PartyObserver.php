<?php

namespace App\Observers;

use App\Events\Party\UpdatedEvent;
use App\Models\Party;

class PartyObserver
{
    public function updated(Party $party): void
    {
        UpdatedEvent::dispatch($party->code);
    }
}
