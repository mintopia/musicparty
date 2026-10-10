<?php

namespace App\Domain\Queue\Actions\Concerns;

use App\Domain\Party\PartyRole;
use App\Domain\Queue\Events\RequestDecisionRecorded;
use App\Domain\Queue\Exceptions\RequestRefusedException;
use App\Domain\Queue\RequestStatus;
use App\Events\Party\PendingRequestResolvedEvent;
use App\Events\Party\RequestDecidedEvent;
use App\Jobs\BroadcastPartyQueue;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\TrackRequest;
use App\Models\User;

trait ModeratesRequests
{
    protected function activeMember(User $actor, Party $party): PartyMember
    {
        $member = $party->memberFor($actor);

        if ($member === null || $member->banned) {
            throw RequestRefusedException::notAllowed();
        }

        return $member;
    }

    protected function isModerator(PartyMember $member): bool
    {
        return in_array($member->role, [PartyRole::Host, PartyRole::Moderator], true);
    }

    protected function lockRequest(Party $party, TrackRequest $request): TrackRequest
    {
        return TrackRequest::query()
            ->where('party_id', $party->id)
            ->whereKey($request->id)
            ->lockForUpdate()
            ->with('requester.user')
            ->firstOrFail();
    }

    protected function decide(Party $party, TrackRequest $request, PartyMember $decider, RequestStatus $next, string $logAction, ?string $reason = null): void
    {
        $previous = $request->status;

        $request->forceFill([
            'status' => $next,
            'decided_by_member_id' => $decider->id,
            'decided_at' => now(),
            'rejection_reason' => $reason,
        ])->save();

        ($this->record)($party, $logAction, $decider->holder(), $request->title, [
            'request_id' => $request->id,
            'requester_member_id' => $request->party_member_id,
            'reason' => $reason,
            'previous_status' => $previous->value,
        ]);
    }

    protected function announceDecision(Party $party, TrackRequest $request, RequestStatus $previous, ?PartyMember $decider = null, ?string $reason = null): void
    {
        if (in_array($request->status, [RequestStatus::Rejected, RequestStatus::Removed], true)) {
            RequestDecisionRecorded::dispatch($party->id, $request->id, $request->status->value);
        }

        if ($previous === RequestStatus::Pending) {
            PendingRequestResolvedEvent::dispatch($party->code, $request->id, $request->status->value);
        }

        if ($request->status === RequestStatus::Queued || $previous === RequestStatus::Queued) {
            BroadcastPartyQueue::dispatch($party->code);
        }

        if ($decider === null || $decider->id !== $request->party_member_id) {
            RequestDecidedEvent::dispatch($party->code, $request->party_member_id, $request->id, $request->status->value, $reason);
        }
    }
}
