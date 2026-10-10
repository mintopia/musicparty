<?php

namespace App\Domain\Party;

use App\Domain\Music\Actions\AuthorisesHost;
use App\Domain\Music\Data\TrackData;
use App\Domain\Party\Models\BlocklistEntry;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Blocklist;
use App\Domain\Queue\Models\Play;
use Illuminate\Database\Eloquent\Collection;

readonly class FallbackPlaylistGate
{
    public const REQUIRED_PLAYABLE_TRACKS = 20;

    public function __construct(private PairingCatalogue $catalogue, private Blocklist $blocklist, private AuthorisesHost $host) {}

    public function check(Party $party): FallbackPlaylistCheck
    {
        $playlistId = $party->fallback_playlist_id;

        if ($playlistId === null || $playlistId === '') {
            return new FallbackPlaylistCheck(0, self::REQUIRED_PLAYABLE_TRACKS);
        }

        $provider = $this->catalogue->provider($party->music_provider);
        $provider->forgetPlaylist($playlistId);

        $recentlyPlayed = $this->recentlyPlayedTrackIds($party);
        $tracks = $provider->playlistTracks($playlistId, $this->host->hostAccountIdFor($party));

        $blocked = $this->blocklist->enabledEntries($party);

        $playable = count(array_filter(
            $tracks,
            fn (TrackData $track): bool => $this->isPlayable($party, $track, $recentlyPlayed, $blocked),
        ));

        return new FallbackPlaylistCheck($playable, self::REQUIRED_PLAYABLE_TRACKS);
    }

    /**
     * @param  Collection<int, BlocklistEntry>|null  $blocked  Pre-loaded enabled Blocklist entries; looked up per call when null.
     */
    public function passesRules(Party $party, TrackData $track, ?Collection $blocked = null): bool
    {
        $seconds = $track->durationMs / 1000;

        return $track->playableInMarket
            && ($party->explicit || ! $track->explicit)
            && ($party->min_song_length === null || $seconds >= $party->min_song_length)
            && ($party->max_song_length === null || $seconds <= $party->max_song_length)
            && ($blocked === null ? $this->blocklist->firstMatch($party, $track) : $this->blocklist->firstMatchIn($blocked, $track)) === null;
    }

    /**
     * @param  array<string, true>  $recentlyPlayed
     * @param  Collection<int, BlocklistEntry>  $blocked
     */
    private function isPlayable(Party $party, TrackData $track, array $recentlyPlayed, Collection $blocked): bool
    {
        return $this->passesRules($party, $track, $blocked) && ! isset($recentlyPlayed[$track->providerTrackId]);
    }

    /**
     * @return array<string, true>
     */
    private function recentlyPlayedTrackIds(Party $party): array
    {
        if ($party->no_repeat_interval === null) {
            return [];
        }

        $ids = Play::query()
            ->where('party_id', $party->id)
            ->where('played_at', '>=', now()->subSeconds($party->no_repeat_interval))
            ->pluck('provider_track_id');

        return array_fill_keys($ids->all(), true);
    }
}
