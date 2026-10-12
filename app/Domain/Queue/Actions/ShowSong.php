<?php

namespace App\Domain\Queue\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Data\SongDetail;
use App\Domain\Queue\Exceptions\RequestRefusedException;
use App\Domain\Queue\Models\Play;
use App\Domain\Queue\Models\TrackRequest;
use Illuminate\Database\Eloquent\Builder;

class ShowSong
{
    private const EAGER_REQUEST = [
        'party',
        'requester.user',
        'votes.member.user',
        'play.memberRatings.member.user',
    ];

    /**
     * @throws RequestRefusedException
     */
    public function __invoke(User $user, Party $party, TrackRequest $trackRequest): SongDetail
    {
        $this->authorise($user, $party);

        abort_unless($trackRequest->party_id === $party->id, 404);

        $trackRequest->load(self::EAGER_REQUEST);

        return $this->detail($user, $party, $trackRequest, $trackRequest->play, $trackRequest->provider_track_id, $trackRequest->isrc);
    }

    /**
     * @throws RequestRefusedException
     */
    public function play(User $user, Party $party, Play $play): SongDetail
    {
        $this->authorise($user, $party);

        abort_unless($play->party_id === $party->id, 404);

        $play->load(['party', 'memberRatings.member.user', 'request' => fn ($query) => $query->with(['party', 'requester.user', 'votes.member.user'])]);

        $trackRequest = $play->request;
        $trackRequest?->setRelation('play', $play);

        return $this->detail($user, $party, $trackRequest, $play, $play->provider_track_id, $trackRequest?->isrc);
    }

    /**
     * @throws RequestRefusedException
     */
    private function authorise(User $user, Party $party): void
    {
        $member = $party->memberFor($user) ?? throw RequestRefusedException::notAMember();

        if ($member->banned) {
            throw RequestRefusedException::banned();
        }
    }

    private function detail(User $user, Party $party, ?TrackRequest $trackRequest, ?Play $play, string $providerTrackId, ?string $isrc): SongDetail
    {
        $requests = TrackRequest::query()
            ->where('party_id', $party->id)
            ->when($trackRequest !== null, fn (Builder $query) => $query->whereKeyNot($trackRequest?->id))
            ->where(function (Builder $query) use ($providerTrackId, $isrc): void {
                $query->where('provider_track_id', $providerTrackId);

                if ($isrc !== null) {
                    $query->orWhere('isrc', $isrc);
                }
            })
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $representedRequestIds = $requests->pluck('id')->when($trackRequest !== null, fn ($ids) => $ids->push($trackRequest?->id))->all();

        $plays = Play::query()
            ->where('party_id', $party->id)
            ->where('provider_track_id', $providerTrackId)
            ->when($play !== null, fn (Builder $query) => $query->whereKeyNot($play?->id))
            ->where(function (Builder $query) use ($representedRequestIds): void {
                $query->whereNull('track_request_id')->orWhereNotIn('track_request_id', $representedRequestIds);
            })
            ->orderBy('played_at')
            ->orderBy('id')
            ->get();

        return new SongDetail($trackRequest, $play, $requests->concat($plays)->values(), $user->can('moderate', $party));
    }
}
