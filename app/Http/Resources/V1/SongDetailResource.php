<?php

namespace App\Http\Resources\V1;

use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\PairingCatalogue;
use App\Domain\Queue\Data\SongDetail;
use App\Domain\Queue\Models\Play;
use App\Domain\Queue\Models\Rating;
use App\Domain\Queue\Models\RequestVote;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\RequestStatus;
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
        $trackRequest = $this->resource->request;
        $play = $this->resource->play;
        $subject = $trackRequest ?? $play;
        $party = $subject?->party;
        $requester = $trackRequest?->requester;
        $playedAt = $play?->played_at->toIso8601String();

        return [
            'kind' => $trackRequest === null ? 'play' : 'request',
            'id' => $subject?->id,
            'play_id' => $play?->id,
            'title' => $subject?->title,
            'artists' => $subject?->artists,
            'album' => $subject?->album,
            'artwork_url' => $subject?->artwork_url,
            'duration_ms' => $subject?->duration_ms,
            'explicit' => $subject?->explicit,
            'provider_track_id' => $subject?->provider_track_id,
            'isrc' => $trackRequest?->isrc,
            'status' => $trackRequest === null ? RequestStatus::Played->value : $trackRequest->status->value,
            'requested_at' => ($trackRequest === null ? $play?->played_at : $trackRequest->created_at)?->toIso8601String(),
            'sent_at' => $trackRequest === null ? $playedAt : $trackRequest->enqueued_at?->toIso8601String(),
            'played_at' => $playedAt,
            'spotify_url' => app(PairingCatalogue::class)->trackUrl($party?->music_provider, (string) $subject?->provider_track_id),
            'requester' => $requester === null ? null : $this->member($requester),
            'votes' => ($trackRequest === null ? collect() : $trackRequest->votes)->map(fn (RequestVote $vote): array => [
                'member' => $this->member($vote->member),
                'direction' => $vote->value > 0 ? 'up' : 'down',
                'voted_at' => $vote->created_at?->toIso8601String(),
            ])->values()->all(),
            'ratings' => $play?->memberRatings->map(fn (Rating $rating): array => [
                'member' => $this->member($rating->member),
                'direction' => $rating->value > 0 ? 'up' : 'down',
                'rated_at' => $rating->created_at?->toIso8601String(),
            ])->values()->all() ?? [],
            'appearances' => $this->resource->appearances->map(fn (TrackRequest|Play $appearance): array => $this->appearance($appearance, (string) $party?->code))->values()->all(),
            'can_moderate' => $this->resource->canModerate,
            'moderate_url' => $this->resource->canModerate && $party !== null
                ? route('parties.blocklist', ['party' => $party->code]).'?'.http_build_query(['type' => 'track_id', 'value' => $subject->provider_track_id, 'name' => $subject->title])
                : null,
        ];
    }

    /**
     * @return array{kind: string, id: int, url: string, requested_at: string|null, sent_at: string|null}
     */
    private function appearance(TrackRequest|Play $appearance, string $code): array
    {
        if ($appearance instanceof Play) {
            return [
                'kind' => 'play',
                'id' => $appearance->id,
                'url' => route('parties.plays.show', ['party' => $code, 'play' => $appearance->id], false),
                'requested_at' => $appearance->played_at->toIso8601String(),
                'sent_at' => $appearance->played_at->toIso8601String(),
            ];
        }

        return [
            'kind' => 'request',
            'id' => $appearance->id,
            'url' => route('parties.songs.show', ['party' => $code, 'trackRequest' => $appearance->id], false),
            'requested_at' => $appearance->created_at?->toIso8601String(),
            'sent_at' => $appearance->enqueued_at?->toIso8601String(),
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
