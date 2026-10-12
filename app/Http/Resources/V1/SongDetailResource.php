<?php

namespace App\Http\Resources\V1;

use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\PairingCatalogue;
use App\Domain\Queue\Data\SongDetail;
use App\Domain\Queue\Models\Rating;
use App\Domain\Queue\Models\RequestVote;
use App\Domain\Queue\Models\TrackRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property SongDetail $resource
 */
class SongDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $song = $this->resource->request;
        $party = $song->party;
        $requester = $song->requester;

        return [
            'id' => $song->id,
            'title' => $song->title,
            'artists' => $song->artists,
            'album' => $song->album,
            'artwork_url' => $song->artwork_url,
            'duration_ms' => $song->duration_ms,
            'explicit' => $song->explicit,
            'provider_track_id' => $song->provider_track_id,
            'isrc' => $song->isrc,
            'status' => $song->status->value,
            'requested_at' => $song->created_at?->toIso8601String(),
            'sent_at' => $song->enqueued_at?->toIso8601String(),
            'spotify_url' => app(PairingCatalogue::class)->trackUrl($party?->music_provider, $song->provider_track_id),
            'requester' => $requester === null ? null : $this->member($requester),
            'votes' => $song->votes->map(fn (RequestVote $vote): array => [
                'member' => $this->member($vote->member),
                'direction' => $vote->value > 0 ? 'up' : 'down',
                'voted_at' => $vote->created_at?->toIso8601String(),
            ])->values()->all(),
            'ratings' => $song->play?->memberRatings->map(fn (Rating $rating): array => [
                'member' => $this->member($rating->member),
                'direction' => $rating->value > 0 ? 'up' : 'down',
                'rated_at' => $rating->created_at?->toIso8601String(),
            ])->values()->all() ?? [],
            'appearances' => $this->resource->appearances->map(fn (TrackRequest $appearance): array => [
                'id' => $appearance->id,
                'requested_at' => $appearance->created_at?->toIso8601String(),
                'sent_at' => $appearance->enqueued_at?->toIso8601String(),
            ])->values()->all(),
            'can_moderate' => $this->resource->canModerate,
            'moderate_url' => $this->resource->canModerate && $party !== null
                ? route('parties.blocklist', ['party' => $party->code]).'?'.http_build_query(['type' => 'track_id', 'value' => $song->provider_track_id, 'name' => $song->title])
                : null,
        ];
    }

    /**
     * @return array{id: int|null, name: string|null}
     */
    private function member(?PartyMember $member): array
    {
        return ['id' => $member?->id, 'name' => $member?->holder()->nickname];
    }
}
