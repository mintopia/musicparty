<?php

namespace App\Domain\Queue\Actions;

use App\Domain\Music\Data\TrackData;
use App\Domain\Music\Exceptions\ProviderTemporaryFailure;
use App\Domain\Music\Exceptions\ProviderUnavailableException;
use App\Domain\Party\FallbackPlaylistGate;
use App\Domain\Party\PairingCatalogue;
use App\Domain\Party\PartyState;
use App\Domain\Queue\RequestStatus;
use App\Models\Party;
use App\Models\Play;
use App\Models\TrackRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

readonly class TopUpFallbackRequests
{
    public function __construct(private PairingCatalogue $catalogue, private FallbackPlaylistGate $gate) {}

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
            $pool = $this->catalogue->provider($party->music_provider)->playlistTracks($playlistId);
        } catch (ProviderTemporaryFailure|ProviderUnavailableException) {
            return 0;
        }

        $pool = Arr::shuffle($pool);

        return DB::transaction(function () use ($party, $pool, $minimum): int {
            $locked = Party::query()->whereKey($party->id)->lockForUpdate()->firstOrFail();

            if ($locked->state !== PartyState::Live) {
                return 0;
            }

            $needed = $minimum - $this->queuedCount($locked);
            $blocked = $this->blockedTrackIds($locked);
            $created = 0;

            foreach ($pool as $track) {
                if ($created >= $needed) {
                    break;
                }

                if (isset($blocked[$track->providerTrackId]) || ! $this->gate->passesRules($locked, $track)) {
                    continue;
                }

                $this->createRequest($locked, $track);
                $blocked[$track->providerTrackId] = true;
                $created++;
            }

            return $created;
        });
    }

    private function queuedCount(Party $party): int
    {
        return TrackRequest::query()->where('party_id', $party->id)->where('status', RequestStatus::Queued)->count();
    }

    /**
     * @return array<string, true>
     */
    private function blockedTrackIds(Party $party): array
    {
        $active = TrackRequest::query()
            ->where('party_id', $party->id)
            ->whereIn('status', [RequestStatus::Pending, RequestStatus::Queued, RequestStatus::UpNext, RequestStatus::Playing])
            ->pluck('provider_track_id');

        $recent = $party->no_repeat_interval === null
            ? collect()
            : Play::query()
                ->where('party_id', $party->id)
                ->where('played_at', '>=', now()->subSeconds($party->no_repeat_interval))
                ->pluck('provider_track_id');

        return array_fill_keys($active->merge($recent)->all(), true);
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
