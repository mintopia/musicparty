<?php

namespace App\Domain\Queue\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Exceptions\RequestRefusedException;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\RequestStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

readonly class ListPendingRequests
{
    /**
     * @return Collection<int, TrackRequest>
     *
     * @throws RequestRefusedException
     */
    public function __invoke(User $actor, Party $party): Collection
    {
        $member = $party->memberFor($actor);

        if ($member === null || $member->banned) {
            throw RequestRefusedException::notAllowed();
        }

        $moderator = Gate::forUser($actor)->allows('moderate', $party);

        return TrackRequest::query()
            ->where('party_id', $party->id)
            ->where('status', RequestStatus::Pending)
            ->unless($moderator, fn ($query) => $query->where('party_member_id', $member->id))
            ->with('requester.user')
            ->withSum('votes as score', 'value')
            ->oldest('id')
            ->get();
    }
}
