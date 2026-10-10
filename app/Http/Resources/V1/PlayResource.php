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
 * @mixin Play
 */
class PlayResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $requester = $this->requester?->user;

        return [
            'id' => $this->id,
            'track' => [
                'title' => $this->title,
                'artists' => $this->artists,
                'album' => $this->album,
                'artwork_url' => $this->artwork_url,
                'duration_ms' => $this->duration_ms,
                'explicit' => $this->explicit,
                'provider_url' => app(PairingCatalogue::class)->trackUrl($this->party?->music_provider, $this->provider_track_id),
            ],
            'requested_by' => ['name' => $requester instanceof User ? $requester->nickname : null],
            'is_mine' => $request->user() !== null && $this->requester?->user_id === $request->user()->id,
            'votes' => (int) $this->request?->votes_count,
            'score' => (int) $this->request?->score,
            'requested_at' => ($this->request instanceof TrackRequest ? $this->request->created_at : $this->played_at)?->toIso8601String(),
            'likes' => (int) $this->likes,
            'dislikes' => (int) $this->dislikes,
            'my_rating' => (int) $this->my_rating,
            'played_at' => $this->played_at->toIso8601String(),
            'decorations' => app(ResolveDecorations::class)($this->resource),
        ];
    }
}
