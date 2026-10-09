<?php

namespace Tests\Fixtures;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class UndocumentedBroadcastEvent implements ShouldBroadcast
{
    public function broadcastOn(): array
    {
        return [];
    }
}
