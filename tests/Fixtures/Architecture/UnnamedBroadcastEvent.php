<?php

namespace Tests\Fixtures\Architecture;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class UnnamedBroadcastEvent implements ShouldBroadcast
{
    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [];
    }

    public function broadcastOn(): array
    {
        return [];
    }
}
