<?php

namespace App\Events\UpcomingSong;

use App\Models\UpcomingSong;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class UpdatedEvent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(protected string $partyCode, protected int $id) {}

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $song = UpcomingSong::query()->findOrFail($this->id);
        $data = $song->toApi();
        $data['id'] = $song->id;
        $data['created_at'] = $song->created_at->toIso8601String();
        $data['updated_at'] = $song->updated_at->toIso8601String();
        $data['vote'] = null;

        return $data;
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("party.{$this->partyCode}"),
        ];
    }
}
