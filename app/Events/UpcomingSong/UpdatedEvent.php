<?php

namespace App\Events\UpcomingSong;

use App\Http\Resources\V1\UpcomingSongResource;
use App\Models\UpcomingSong;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UpdatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(protected UpcomingSong $song)
    {
        //
    }

    public function broadcastWith()
    {
        $data = $this->song->toApi();
        $data['id'] = $this->song->id;
        $data['created_at'] = $this->song->created_at->toIso8601String();
        $data['updated_at'] = $this->song->updated_at->toIso8601String();
        $data['vote'] = null;
        return $data;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("party.{$this->song->party->code}"),
        ];
    }
}
