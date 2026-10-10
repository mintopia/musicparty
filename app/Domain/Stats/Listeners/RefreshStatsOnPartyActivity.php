<?php

namespace App\Domain\Stats\Listeners;

use App\Domain\Queue\Events\RequestCreated;
use App\Domain\Queue\Events\RequestDecisionRecorded;
use App\Domain\Queue\Events\TrackEnded;
use App\Domain\Queue\Events\VoteCast;
use App\Domain\Stats\Actions\RefreshPartyStats;
use App\Models\Party;

readonly class RefreshStatsOnPartyActivity
{
    public function __construct(private RefreshPartyStats $refresh) {}

    public function handle(RequestCreated|VoteCast|TrackEnded|RequestDecisionRecorded $event): void
    {
        ($this->refresh)($event instanceof RequestDecisionRecorded ? Party::query()->findOrFail($event->partyId) : $event->party);
    }
}
