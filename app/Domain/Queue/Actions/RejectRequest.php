<?php

namespace App\Domain\Queue\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Party\Actions\RecordPartyLogEntry;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Actions\Concerns\ModeratesRequests;
use App\Domain\Queue\Exceptions\RequestRefusedException;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\RequestStatus;
use Illuminate\Support\Facades\DB;

readonly class RejectRequest
{
    use ModeratesRequests;

    public function __construct(private RecordPartyLogEntry $record) {}

    /**
     * @throws RequestRefusedException
     */
    public function __invoke(User $actor, Party $party, TrackRequest $request, ?string $reason = null): TrackRequest
    {
        $reason = $reason === null || trim($reason) === '' ? null : trim($reason);

        $request = DB::transaction(function () use ($actor, $party, $request, $reason): TrackRequest {
            $member = $this->activeMember($actor, $party);

            if (! $this->isModerator($member)) {
                throw RequestRefusedException::notAllowed();
            }

            $locked = $this->lockRequest($party, $request);

            if ($locked->status !== RequestStatus::Pending) {
                throw RequestRefusedException::notPending();
            }

            $this->decide($party, $locked, $member, RequestStatus::Rejected, 'request.rejected', $reason);

            return $locked;
        });

        $this->announceDecision($party, $request, RequestStatus::Pending, reason: $reason);

        return $request;
    }
}
