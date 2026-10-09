<?php

namespace App\Domain\Party\Actions;

use App\Domain\Queue\Broadcast\PartyQueueSnapshot;
use App\Models\Party;
use App\Models\Play;

readonly class ShowPartyTv
{
    public function __construct(private PartyQueueSnapshot $snapshot) {}

    /**
     * @return array{party: array{code: string, name: string, state: string, joinUrl: string}, nowPlaying: array<string, mixed>|null, upNext: array<string, mixed>|null, sequence: int, startedAt: string|null}
     */
    public function __invoke(Party $party): array
    {
        $playback = $this->snapshot->build($party);

        $nowPlayingId = $playback['now_playing']['id'] ?? null;
        $startedAt = $nowPlayingId === null
            ? null
            : Play::query()->where('track_request_id', $nowPlayingId)->latest('played_at')->first()?->played_at;

        return [
            'party' => [
                'code' => $party->code,
                'name' => $party->name,
                'state' => $party->state->value,
                'joinUrl' => route('parties.show', ['party' => $party->code]),
            ],
            'nowPlaying' => $playback['now_playing'],
            'upNext' => $playback['up_next'],
            'sequence' => $playback['sequence'],
            'startedAt' => $startedAt?->toIso8601String(),
        ];
    }
}
