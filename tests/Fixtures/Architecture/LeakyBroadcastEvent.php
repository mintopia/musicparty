<?php

namespace Tests\Fixtures\Architecture;

use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class LeakyBroadcastEvent implements ShouldBroadcastNow
{
    public function __construct(protected int $userId) {}

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->userId,
            'access_token' => 'abc',
            'email' => 'a@example.test',
        ];
    }

    public function broadcastOn(): array
    {
        return [];
    }
}
