<?php

namespace App\Events;

use App\Models\Artist;
use App\Models\Party;
use App\Models\Song;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SpotifyStatusUpdatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(protected Party $party)
    {
        //
    }

    public function broadcastWith()
    {
        return [
            'party' => (object) [
                'name' => $this->party->name,
                'code' => $this->party->code,
            ],
            'song' => $this->getSongData($this->party->song),
            'progress_ms' => $this->getProgress($this->party->user),
        ];
    }

    protected function getProgress(User $user): ?int
    {
        return $user->status->progress_ms ?? null;
    }

    protected function getSongData(?Song $song): ?object
    {
        if (!$song) {
            return null;
        }

        return (object) [
            'spotify_id' => $song->spotify_id,
            'name' => $song->name,
            'length' => $song->length,
            'album' => (object) [
                'spotify_id' => $song->album->spotify_id,
                'name' => $song->album->name,
                'image_url' => $song->album->image_url,
            ],
            'artists' => $song->artists->map(function (Artist $artist) {
                return (object) [
                    'spotify_id' => $artist->spotify_id,
                    'name' => $artist->name,
                ];
            })
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel("party.{$this->party->code}");
    }
}
