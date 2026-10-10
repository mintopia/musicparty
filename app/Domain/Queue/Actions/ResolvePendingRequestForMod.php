<?php

namespace App\Domain\Queue\Actions;

use App\Domain\Party\Actions\RecordPartyLogEntry;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\RequestStatus;
use App\Events\Party\PendingRequestResolvedEvent;
use App\Events\Party\RequestDecidedEvent;
use App\Jobs\BroadcastPartyQueue;
use App\Jobs\StartPlayback;
use App\Models\TrackRequest;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

readonly class ResolvePendingRequestForMod
{
    public function __construct(private RecordPartyLogEntry $record) {}

    /**
     * Returns null when the Request is no longer Pending.
     */
    public function __invoke(Party $party, int $requestId, RequestStatus $next, string $systemActor, ?string $reason = null): ?TrackRequest
    {
        if (! in_array($next, [RequestStatus::Queued, RequestStatus::Rejected], true)) {
            throw new InvalidArgumentException('A Mod can only approve or reject a Pending Request.');
        }

        $request = DB::transaction(function () use ($party, $requestId, $next, $systemActor, $reason): ?TrackRequest {
            $locked = TrackRequest::query()->where('party_id', $party->id)->whereKey($requestId)->lockForUpdate()->first();

            if ($locked === null || $locked->status !== RequestStatus::Pending) {
                return null;
            }

            $locked->forceFill([
                'status' => $next,
                'decided_at' => now(),
                'rejection_reason' => $next === RequestStatus::Rejected ? $reason : null,
            ])->save();

            ($this->record)($party, $next === RequestStatus::Queued ? 'mod.request_approved' : 'mod.request_rejected', subject: $locked->title, details: [
                'request_id' => $locked->id,
                'requester_member_id' => $locked->party_member_id,
                'reason' => $reason,
                'previous_status' => RequestStatus::Pending->value,
            ], systemActor: $systemActor);

            return $locked;
        });

        if ($request === null) {
            return null;
        }

        PendingRequestResolvedEvent::dispatch($party->code, $request->id, $request->status->value);

        if ($request->party_member_id !== null) {
            RequestDecidedEvent::dispatch($party->code, $request->party_member_id, $request->id, $request->status->value, $reason);
        }

        if ($request->status === RequestStatus::Queued) {
            BroadcastPartyQueue::dispatch($party->code);
            StartPlayback::dispatch($party->code);
        }

        return $request;
    }
}
