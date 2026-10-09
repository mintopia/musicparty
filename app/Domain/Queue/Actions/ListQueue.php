<?php

namespace App\Domain\Queue\Actions;

use App\Domain\Queue\RequestStatus;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\TrackRequest;
use Illuminate\Database\Eloquent\Collection;

class ListQueue
{
    /**
     * @return Collection<int, TrackRequest>
     */
    public function __invoke(Party $party, ?PartyMember $viewer = null): Collection
    {
        return TrackRequest::query()
            ->where('party_id', $party->id)
            ->where('status', RequestStatus::Queued)
            ->with('requester.user')
            ->withSum('votes as score', 'value')
            ->withSum(['votes as my_vote' => fn ($query) => $query->where('party_member_id', $viewer?->id)], 'value')
            ->orderByRaw('COALESCE(score, 0) desc')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }
}
