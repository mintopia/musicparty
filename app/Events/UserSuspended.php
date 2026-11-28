<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class UserSuspended implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(public int $userId) {}

    public function broadcastAs(): string
    {
        return 'UserSuspended';
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("user.{$this->userId}");
    }
}
