<?php

namespace Tests\Fixtures\Architecture;

use App\Domain\Party\Models\Party;
use Laravel\Reverb\Events\MessageReceived;

class ReverbListenerLeaker
{
    public function handle(MessageReceived $received): void
    {
        Party::findByCode($received->message);
    }
}
