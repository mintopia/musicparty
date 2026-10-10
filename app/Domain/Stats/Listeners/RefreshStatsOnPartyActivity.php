<?php

namespace App\Domain\Stats\Listeners;

use App\Domain\Queue\Events\RequestCreated;
use App\Domain\Queue\Events\RequestDecisionRecorded;
use App\Domain\Queue\Events\TrackEnded;
use App\Domain\Queue\Events\VoteCast;
use App\Domain\Stats\Jobs\RefreshPartyStatsJob;

readonly class RefreshStatsOnPartyActivity
{
    public function handle(RequestCreated|VoteCast|TrackEnded|RequestDecisionRecorded $event): void
    {
        RefreshPartyStatsJob::dispatch($event instanceof RequestDecisionRecorded ? $event->partyId : $event->party->id);
    }
}
