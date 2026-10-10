<?php

namespace App\Domain\Music\Listeners;

use App\Domain\Music\Actions\AppendPlayToHistory;
use App\Domain\Music\Contracts\MusicProvider;
use App\Domain\Queue\Events\TrackStarted;

readonly class AppendStartedTrackToHistory
{
    public function __construct(private AppendPlayToHistory $append, private MusicProvider $provider) {}

    public function handle(TrackStarted $event): void
    {
        ($this->append)($event->party, $event->request->provider_track_id, $this->provider);
    }
}
