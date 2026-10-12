<?php

namespace App\Domain\Queue\Actions;

use App\Domain\Party\Actions\RecordPartyLogEntry;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Jobs\BroadcastPartyQueue;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\RequestStatus;
use Illuminate\Support\Facades\DB;

readonly class RemoveRequestForMod
{
    public function __construct(private RecordPartyLogEntry $record) {}

    /**
     * Returns null when the Request is no longer Queued.
     */
    public function __invoke(Party $party, int $requestId, string $systemActor): ?TrackRequest
    {
        $request = DB::transaction(function () use ($party, $requestId, $systemActor): ?TrackRequest {
            $locked = TrackRequest::query()->where('party_id', $party->id)->whereKey($requestId)->lockForUpdate()->first();

            if ($locked === null || $locked->status !== RequestStatus::Queued) {
                return null;
            }

            $locked->forceFill(['status' => RequestStatus::Removed, 'decided_at' => now()])->save();

            ($this->record)($party, 'mod.request_removed', subject: $locked->title, details: [
                'request_id' => $locked->id,
                'previous_status' => RequestStatus::Queued->value,
            ], systemActor: $systemActor);

            return $locked;
        });

        if ($request !== null) {
            BroadcastPartyQueue::dispatch($party->code);
        }

        return $request;
    }
}
