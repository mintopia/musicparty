<?php

namespace App\Domain\Music\Listeners;

use App\Domain\Music\Actions\AppendPlayToHistory;
use App\Domain\Party\PairingCatalogue;
use App\Domain\Queue\Events\TrackStarted;
use App\Domain\Queue\Models\Play;

readonly class AppendStartedTrackToHistory
{
    public function __construct(private AppendPlayToHistory $append, private PairingCatalogue $catalogue) {}

    public function handle(TrackStarted $event): void
    {
        $playId = Play::query()->where('track_request_id', $event->request->id)->value('id');

        ($this->append)($event->party, $event->request->provider_track_id, $this->catalogue->provider($event->party->music_provider), is_int($playId) ? $playId : null);
    }
}
