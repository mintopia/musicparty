<?php

namespace App\Domain\Queue\Actions;

use App\Domain\Music\Actions\AuthorisesHost;
use App\Domain\Music\Data\TrackData;
use App\Domain\Music\Exceptions\ProviderTemporaryFailure;
use App\Domain\Music\Exceptions\ProviderUnavailableException;
use App\Domain\Party\Actions\RecordPartyLogEntry;
use App\Domain\Party\FallbackPlaylistGate;
use App\Domain\Party\Models\Party;
use App\Domain\Party\Models\PartyLogEntry;
use App\Domain\Party\PairingCatalogue;
use App\Domain\Party\PartyState;
use App\Domain\Queue\RequestStatus;
use App\Models\Play;
use App\Models\TrackRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

readonly class TopUpFallbackRequests
{
    public function __construct(
        private PairingCatalogue $catalogue,
        private FallbackPlaylistGate $gate,
        private RecordPartyLogEntry $record,
        private AuthorisesHost $host,
    ) {}

    /**
     * Returns how many Fallback Requests were created.
     */
    public function __invoke(Party $party): int
    {
        $playlistId = $party->fallback_playlist_id;
        $minimum = (int) config('musicparty.fallback_minimum_queue', 5);

        if ($party->state !== PartyState::Live || $playlistId === null || $playlistId === '' || $this->queuedCount($party) >= $minimum) {
            return 0;
        }

        try {
            $pool = $this->catalogue->provider($party->music_provider)->playlistTracks($playlistId, $this->host->hostAccountIdFor($party));
        } catch (ProviderTemporaryFailure|ProviderUnavailableException) {
            return 0;
        }

        $pool = Arr::shuffle($pool);

        $result = DB::transaction(function () use ($party, $pool, $minimum): ?array {
            $locked = Party::query()->whereKey($party->id)->lockForUpdate()->firstOrFail();

            if ($locked->state !== PartyState::Live) {
                return null;
            }

            $needed = $minimum - $this->queuedCount($locked);
            $active = $this->activeTrackIds($locked);
            $recent = $this->recentTrackIds($locked);
            $reallowed = false;

            $created = $this->fill($locked, $pool, $needed, $active + $recent);

            if ($created === 0 && $this->queuedCount($locked) === 0) {
                $created = $this->fill($locked, $pool, $needed, $active);
                $reallowed = $created > 0;
            }

            $remaining = $this->eligibleCount($locked, $pool, $active + $recent);

            return ['created' => $created, 'reallowed' => $reallowed, 'remaining' => $remaining, 'empty' => $this->queuedCount($locked) === 0];
        });

        if ($result === null) {
            return 0;
        }

        $this->recordHealth($party, $result['reallowed'], $result['remaining'], $result['empty'], $minimum);

        return $result['created'];
    }

    /**
     * @param  array<int, TrackData>  $pool
     * @param  array<string, true>  $blocked
     */
    private function fill(Party $party, array $pool, int $needed, array $blocked): int
    {
        $created = 0;

        foreach ($pool as $track) {
            if ($created >= $needed) {
                break;
            }

            if (isset($blocked[$track->providerTrackId]) || ! $this->gate->passesRules($party, $track)) {
                continue;
            }

            $this->createRequest($party, $track);
            $blocked[$track->providerTrackId] = true;
            $created++;
        }

        return $created;
    }

    /**
     * @param  array<int, TrackData>  $pool
     * @param  array<string, true>  $blocked
     */
    private function eligibleCount(Party $party, array $pool, array $blocked): int
    {
        return count(array_filter(
            $pool,
            fn (TrackData $track): bool => ! isset($blocked[$track->providerTrackId]) && $this->gate->passesRules($party, $track),
        ));
    }

    private function recordHealth(Party $party, bool $reallowed, int $remaining, bool $empty, int $minimum): void
    {
        $event = match (true) {
            $empty => 'fallback.exhausted',
            $reallowed => 'fallback.recent_plays_reallowed',
            $remaining < $minimum => 'fallback.running_low',
            default => 'fallback.healthy',
        };

        $last = PartyLogEntry::query()
            ->where('party_id', $party->id)
            ->where('action', 'like', 'fallback.%')
            ->latest('id')
            ->value('action');

        if ($event === 'fallback.healthy') {
            if ($last !== null && $last !== 'fallback.healthy') {
                ($this->record)($party, $event, systemActor: 'fallback');
            }

            return;
        }

        if ($last !== $event) {
            ($this->record)($party, $event, details: ['eligible' => $remaining, 'minimum' => $minimum], systemActor: 'fallback');
        }
    }

    private function queuedCount(Party $party): int
    {
        return TrackRequest::query()->where('party_id', $party->id)->where('status', RequestStatus::Queued)->count();
    }

    /**
     * @return array<string, true>
     */
    private function activeTrackIds(Party $party): array
    {
        $active = TrackRequest::query()
            ->where('party_id', $party->id)
            ->whereIn('status', [RequestStatus::Pending, RequestStatus::Queued, RequestStatus::UpNext, RequestStatus::Playing])
            ->pluck('provider_track_id');

        return array_fill_keys($active->all(), true);
    }

    /**
     * @return array<string, true>
     */
    private function recentTrackIds(Party $party): array
    {
        if ($party->no_repeat_interval === null) {
            return [];
        }

        $recent = Play::query()
            ->where('party_id', $party->id)
            ->where('played_at', '>=', now()->subSeconds($party->no_repeat_interval))
            ->pluck('provider_track_id');

        return array_fill_keys($recent->all(), true);
    }

    private function createRequest(Party $party, TrackData $track): void
    {
        TrackRequest::query()->create([
            'party_id' => $party->id,
            'party_member_id' => null,
            'provider_track_id' => $track->providerTrackId,
            'title' => $track->name,
            'artists' => array_map(fn ($artist): string => $artist->name, $track->artists),
            'album' => $track->album->name,
            'artwork_url' => $track->coverArtUrls[0] ?? null,
            'duration_ms' => $track->durationMs,
            'explicit' => $track->explicit,
            'status' => RequestStatus::Queued,
        ]);
    }
}
