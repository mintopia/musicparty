<?php

namespace Tests\Fixtures\Architecture;

use App\Models\User;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class SerialisingBroadcastEvent implements ShouldBroadcast
{
    use SerializesModels;

    public function __construct(public User $user) {}

    public function broadcastOn(): array
    {
        return [];
    }
}
