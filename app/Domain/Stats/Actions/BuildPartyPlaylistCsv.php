<?php

namespace App\Domain\Stats\Actions;

use App\Domain\Party\Models\Party;
use App\Domain\Party\PairingCatalogue;
use App\Domain\Party\PartyState;
use App\Domain\Queue\Exceptions\RequestRefusedException;
use App\Domain\Queue\Models\Play;
use Generator;

readonly class BuildPartyPlaylistCsv
{
    public const array HEADER = ['position', 'played_at', 'title', 'artists', 'album', 'requested_by', 'score', 'track_url'];

    public function __construct(private PairingCatalogue $catalogue) {}

    /**
     * @return Generator<int, list<string|int>>
     */
    public function __invoke(Party $party): Generator
    {
        if ($party->state !== PartyState::Ended) {
            throw RequestRefusedException::partyNotEnded();
        }

        return $this->rows($party);
    }

    /**
     * @return Generator<int, list<string|int>>
     */
    private function rows(Party $party): Generator
    {
        yield self::HEADER;

        $plays = Play::query()
            ->where('party_id', $party->id)
            ->withHistoryRelations()
            ->orderBy('played_at')
            ->orderBy('id')
            ->lazyById();

        $position = 0;

        foreach ($plays as $play) {
            $position++;

            yield [
                $position,
                $play->played_at->toIso8601String(),
                $this->safe($play->title),
                $this->safe(implode(', ', $play->artists)),
                $this->safe((string) $play->album),
                $this->safe((string) $play->requester?->user?->nickname),
                (int) $play->request?->score,
                $this->catalogue->trackUrl($party->music_provider, $play->provider_track_id) ?? '',
            ];
        }
    }

    private function safe(string $value): string
    {
        return $value !== '' && str_contains("=+-@\t\r", $value[0]) ? "'".$value : $value;
    }
}
