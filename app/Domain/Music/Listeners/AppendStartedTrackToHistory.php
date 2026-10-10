<?php

namespace App\Domain\Music\Listeners;

use App\Domain\Music\Actions\AppendPlayToHistory;
use App\Domain\Party\PairingCatalogue;
use App\Domain\Queue\Events\TrackStarted;

readonly class AppendStartedTrackToHistory
{
    public function __construct(private AppendPlayToHistory $append, private PairingCatalogue $catalogue) {}

    public function handle(TrackStarted $event): void
    {
        ($this->append)($event->party, $event->request->provider_track_id, $this->catalogue->provider($event->party->music_provider));
    }
}
