<?php

namespace App\Domain\Queue\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Actions\RecordPartyLogEntry;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Actions\Concerns\ModeratesRequests;
use App\Domain\Queue\Exceptions\RequestRefusedException;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\RequestStatus;
use Illuminate\Support\Facades\DB;

readonly class RemoveRequest
{
    use ModeratesRequests;

    public function __construct(private RecordPartyLogEntry $record) {}

    /**
     * @throws RequestRefusedException
     */
    public function __invoke(User $actor, Party $party, TrackRequest $request): TrackRequest
    {
        /** @var array{TrackRequest, RequestStatus, PartyMember} $result */
        $result = DB::transaction(function () use ($actor, $party, $request): array {
            $member = $this->activeMember($actor, $party);
            $locked = $this->lockRequest($party, $request);

            if (! $this->isModerator($member) && $locked->party_member_id !== $member->id) {
                throw RequestRefusedException::notAllowed();
            }

            $previous = $locked->status;

            if (! in_array($previous, [RequestStatus::Queued, RequestStatus::Pending], true)) {
                throw RequestRefusedException::notRemovable();
            }

            $this->decide($party, $locked, $member, RequestStatus::Removed, 'request.removed');

            return [$locked, $previous, $member];
        });
        [$request, $previous, $member] = $result;

        $this->announceDecision($party, $request, $previous, $member);

        return $request;
    }
}
