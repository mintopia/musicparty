<?php

namespace App\Domain\Party;

use App\Domain\Music\Data\TrackData;
use App\Domain\Queue\Blocklist;
use App\Models\BlocklistEntry;
use App\Models\Party;
use App\Models\PlayedSong;
use Illuminate\Database\Eloquent\Collection;

readonly class FallbackPlaylistGate
{
    public const REQUIRED_PLAYABLE_TRACKS = 20;

    public function __construct(private PairingCatalogue $catalogue, private Blocklist $blocklist) {}

    public function check(Party $party): FallbackPlaylistCheck
    {
        $playlistId = $party->fallback_playlist_id;

        if ($playlistId === null || $playlistId === '') {
            return new FallbackPlaylistCheck(0, self::REQUIRED_PLAYABLE_TRACKS);
        }

        $recentlyPlayed = $this->recentlyPlayedTrackIds($party);
        $tracks = $this->catalogue->provider($party->music_provider)->playlistTracks($playlistId);

        $blocked = $this->blocklist->enabledEntries($party);

        $playable = count(array_filter(
            $tracks,
            fn (TrackData $track): bool => $this->isPlayable($party, $track, $recentlyPlayed, $blocked),
        ));

        return new FallbackPlaylistCheck($playable, self::REQUIRED_PLAYABLE_TRACKS);
    }

    /**
     * @param  array<string, true>  $recentlyPlayed
     * @param  Collection<int, BlocklistEntry>  $blocked
     */
    private function isPlayable(Party $party, TrackData $track, array $recentlyPlayed, Collection $blocked): bool
    {
        $seconds = $track->durationMs / 1000;

        return $track->playableInMarket
            && ($party->explicit || ! $track->explicit)
            && ($party->min_song_length === null || $seconds >= $party->min_song_length)
            && ($party->max_song_length === null || $seconds <= $party->max_song_length)
            && ! isset($recentlyPlayed[$track->providerTrackId])
            && $this->blocklist->firstMatchIn($blocked, $track) === null;
    }

    /**
     * @return array<string, true>
     */
    private function recentlyPlayedTrackIds(Party $party): array
    {
        if ($party->no_repeat_interval === null) {
            return [];
        }

        $ids = PlayedSong::query()
            ->join('songs', 'songs.id', '=', 'played_songs.song_id')
            ->where('played_songs.party_id', $party->id)
            ->where('played_songs.played_at', '>=', now()->subSeconds($party->no_repeat_interval))
            ->pluck('songs.spotify_id');

        return array_fill_keys($ids->all(), true);
    }
}
