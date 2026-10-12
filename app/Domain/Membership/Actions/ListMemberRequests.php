<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Membership\Models\PartyMember;
use App\Domain\Queue\Models\TrackRequest;
use Illuminate\Database\Eloquent\Collection;

readonly class ListMemberRequests
{
    public const LIMIT = 50;

    /**
     * @return Collection<int, TrackRequest>
     */
    public function __invoke(PartyMember $member): Collection
    {
        return TrackRequest::query()
            ->where('party_id', $member->party_id)
            ->where('party_member_id', $member->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get();
    }
}
