<?php

namespace App\Domain\Party\Actions;

use App\Domain\Queue\Broadcast\PartyQueueSnapshot;
use App\Models\Party;

readonly class ShowPartyTv
{
    public function __construct(private PartyQueueSnapshot $snapshot) {}

    /**
     * @return array{party: array{code: string, name: string, state: string, joinUrl: string}, nowPlaying: array<string, mixed>|null, upNext: array<string, mixed>|null, sequence: int}
     */
    public function __invoke(Party $party): array
    {
        $playback = $this->snapshot->build($party);

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
        ];
    }
}
