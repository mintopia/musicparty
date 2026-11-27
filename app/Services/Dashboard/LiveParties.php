<?php

namespace App\Services\Dashboard;

use App\Models\Party;
use App\Services\Music\MusicProviderFactory;
use App\Services\PlaybackStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * @phpstan-type LiveParty array{code: string, name: string, url: string, owner: string, state: string, stateLabel: string|null, stateDetail: string|null, track: array{name: string, artists: string, image: string|null, lastKnown: bool}|null, guestsOnline: int|null, requestsWaiting: int, flagged: int}
 */
class LiveParties
{
    private const int LIVE_REPORT_SECONDS = 1800;

    public function __construct(
        private readonly ReverbStatus $reverb,
        private readonly PlaybackStatus $status,
        private readonly MusicProviderFactory $providers,
        private readonly PlayerStaleness $staleness,
    ) {}

    /**
     * @return Collection<int, array{model: Party, party: LiveParty}>
     */
    public function all(): Collection
    {
        $rows = Party::query()
            ->where('active', true)
            ->with(['user', 'song.album', 'song.artists'])
            ->withCount([
                'upcoming as waiting_count' => fn ($query) => $query->whereNull('queued_at')->whereNull('flagged_at'),
                'upcoming as flagged_count' => fn ($query) => $query->whereNull('queued_at')->whereNotNull('flagged_at'),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Party $party): array => ['model' => $party, 'guests' => $this->reverb->subscribers("party.{$party->code}")])
            ->filter(fn (array $row): bool => $this->isLive($row['model'], $row['guests']));

        return $rows->map(fn (array $row): array => ['model' => $row['model'], 'party' => $this->liveParty($row['model'], $row['guests'])])->values();
    }

    private function isLive(Party $party, ?int $guests): bool
    {
        return ($guests ?? 0) > 0
            || ($party->playback_received_at !== null && $party->playback_received_at->greaterThan(now()->subSeconds(self::LIVE_REPORT_SECONDS)));
    }

    /**
     * @return LiveParty
     */
    private function liveParty(Party $party, ?int $guests): array
    {
        [$state, $label, $detail] = $this->playback($party);
        $song = $party->song;

        return [
            'code' => $party->code,
            'name' => $party->name,
            'url' => route('parties.dashboard', $party->code),
            'owner' => $party->user->nickname,
            'state' => $state,
            'stateLabel' => $label,
            'stateDetail' => $detail,
            'track' => $song === null ? null : [
                'name' => $song->name,
                'artists' => $song->artists->pluck('name')->implode(', '),
                'image' => $song->album->image_url,
                'lastKnown' => $state === 'stale',
            ],
            'guestsOnline' => $guests,
            'requestsWaiting' => (int) $party->getAttribute('waiting_count'),
            'flagged' => (int) $party->getAttribute('flagged_count'),
        ];
    }

    /**
     * @return array{0: string, 1: string|null, 2: string|null}
     */
    private function playback(Party $party): array
    {
        if ($this->providers->partyNeedsRelink($party)) {
            return ['error', 'Re-link needed', "{$party->user->nickname}'s Spotify link has expired."];
        }
        if ($this->staleness->isStale($party)) {
            return ['stale', null, 'No report for '.$this->staleness->words($party->playback_received_at)];
        }
        $reported = $party->playback_received_at === null ? null : 'Paused for '.$party->playback_received_at->diffForHumans(null, CarbonInterface::DIFF_ABSOLUTE);
        if ($party->song_id !== null && $this->status->recall($party)?->isPlaying === true) {
            return ['playing', null, null];
        }
        if ($party->song_id !== null) {
            return ['paused', null, $reported];
        }

        return ['idle', null, $party->getAttribute('waiting_count') > 0 ? null : 'Nothing queued'];
    }
}
