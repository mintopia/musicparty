<?php

namespace App\Http\Resources\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Mod\Actions\ResolveDecorations;
use App\Domain\Party\PairingCatalogue;
use App\Domain\Queue\Models\Play;
use App\Domain\Queue\Models\TrackRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Play|TrackRequest
 */
class HistoryEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof Play) {
            return [...(new PlayResource($this->resource))->toArray($request), 'kind' => 'play', 'request_id' => $this->resource->track_request_id];
        }

        $trackRequest = $this->resource;
        $requester = $trackRequest->requester?->user;

        return [
            'id' => $trackRequest->id,
            'kind' => 'unsent',
            'request_id' => $trackRequest->id,
            'track' => [
                'title' => $trackRequest->title,
                'artists' => $trackRequest->artists,
                'album' => $trackRequest->album,
                'artwork_url' => $trackRequest->artwork_url,
                'duration_ms' => $trackRequest->duration_ms,
                'explicit' => $trackRequest->explicit,
                'provider_url' => app(PairingCatalogue::class)->trackUrl($trackRequest->party?->music_provider, $trackRequest->provider_track_id),
            ],
            'requested_by' => ['name' => $requester instanceof User ? $requester->nickname : null],
            'is_mine' => $request->user() !== null && $trackRequest->requester?->user_id === $request->user()->id,
            'votes' => (int) $trackRequest->votes_count,
            'score' => (int) $trackRequest->score,
            'requested_at' => $trackRequest->created_at?->toIso8601String(),
            'likes' => 0,
            'dislikes' => 0,
            'my_rating' => 0,
            'played_at' => null,
            'decorations' => app(ResolveDecorations::class)($trackRequest),
        ];
    }
}
