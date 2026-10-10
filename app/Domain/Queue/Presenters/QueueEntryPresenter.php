<?php

namespace App\Domain\Queue\Presenters;

use App\Domain\Identity\Models\User;
use App\Domain\Mod\Actions\ResolveDecorations;
use App\Domain\Party\PairingCatalogue;
use App\Domain\Queue\Models\TrackRequest;

readonly class QueueEntryPresenter
{
    public function __construct(private ResolveDecorations $decorations, private PairingCatalogue $catalogue) {}

    /**
     * The public fields of a Queue entry, identical for the API, page props and broadcasts.
     *
     * @return array<string, mixed>
     */
    public function __invoke(TrackRequest $request): array
    {
        $requester = $request->requester?->user;
        $play = $request->relationLoaded('play') ? $request->play : null;

        return [
            'id' => $request->id,
            'track' => [
                'title' => $request->title,
                'artists' => $request->artists,
                'album' => $request->album,
                'artwork_url' => $request->artwork_url,
                'duration_ms' => $request->duration_ms,
                'explicit' => $request->explicit,
                'provider_url' => $this->catalogue->trackUrl($request->party?->music_provider, $request->provider_track_id),
            ],
            'status' => $request->status->value,
            'score' => (int) $request->score,
            ...($request->relationLoaded('play') ? ['likes' => (int) $play?->likes, 'dislikes' => (int) $play?->dislikes, 'play_id' => $play?->id] : []),
            'requested_by' => ['name' => $requester instanceof User ? $requester->nickname : null],
            'decorations' => ($this->decorations)($request),
        ];
    }
}
