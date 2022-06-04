<?php

namespace App\Events\UpcomingSong;

use App\Models\UpcomingSong;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UpdatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(protected UpcomingSong $song)
    {
        //
    }

    public function broadcastWith()
    {
        $data = $this->song->song->toApi();
        $data['votes'] = $this->song->votes;
        $data['created_at'] = $this->song->created_at->toIso8601String();
        $data['id'] = $this->song->id;
        return $data;
    }

    public function broadcastOn()
    {
        return new PrivateChannel("party.{$this->song->party->code}");
    }
}
