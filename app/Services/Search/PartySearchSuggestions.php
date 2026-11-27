<?php

namespace App\Services\Search;

use App\Models\Party;
use Illuminate\Support\Facades\DB;

class PartySearchSuggestions
{
    private const int TONIGHT_HOURS = 12;

    /**
     * @return list<array{id: string, name: string, image_url: string|null, requests: int}>
     */
    public function topArtists(Party $party, int $limit): array
    {
        $rows = DB::table('upcoming_songs')
            ->join('artist_song', 'artist_song.song_id', '=', 'upcoming_songs.song_id')
            ->join('artists', 'artists.id', '=', 'artist_song.artist_id')
            ->where('upcoming_songs.party_id', $party->id)
            ->where('upcoming_songs.created_at', '>=', now()->subHours(self::TONIGHT_HOURS))
            ->groupBy('artists.id', 'artists.provider_artist_id', 'artists.name')
            ->selectRaw('artists.provider_artist_id as id, artists.name as name, count(distinct upcoming_songs.id) as requests')
            ->orderByDesc('requests')
            ->orderBy('artists.name')
            ->limit($limit)
            ->get();

        $covers = $this->coversFor($party, array_values($rows->pluck('id')->map(fn (mixed $id): string => (string) $id)->all()));

        return array_values($rows->map(fn (object $row): array => [
            'id' => (string) $row->id,
            'name' => (string) $row->name,
            'image_url' => $covers[(string) $row->id] ?? null,
            'requests' => (int) $row->requests,
        ])->all());
    }

    /**
     * The cover of each artist's most recently requested track at this party, standing in for an artist photo.
     *
     * @param  list<string>  $providerArtistIds
     * @return array<string, string>
     */
    protected function coversFor(Party $party, array $providerArtistIds): array
    {
        if ($providerArtistIds === []) {
            return [];
        }

        $covers = [];
        $rows = DB::table('upcoming_songs')
            ->join('songs', 'songs.id', '=', 'upcoming_songs.song_id')
            ->join('albums', 'albums.id', '=', 'songs.album_id')
            ->join('artist_song', 'artist_song.song_id', '=', 'upcoming_songs.song_id')
            ->join('artists', 'artists.id', '=', 'artist_song.artist_id')
            ->where('upcoming_songs.party_id', $party->id)
            ->whereIn('artists.provider_artist_id', $providerArtistIds)
            ->orderByDesc('upcoming_songs.id')
            ->get(['artists.provider_artist_id as artist_id', 'albums.image_url as image_url']);

        foreach ($rows as $row) {
            $covers[(string) $row->artist_id] ??= (string) $row->image_url;
        }

        return array_filter($covers, fn (string $url): bool => $url !== '');
    }

    /**
     * @return array{name: string, artist: string, image_url: string|null, query: string}|null
     */
    public function nowPlaying(Party $party): ?array
    {
        $song = $party->song;
        $artist = $song?->artists()->orderBy('artists.id')->first();
        if ($song === null || $artist === null) {
            return null;
        }

        return [
            'name' => $song->name,
            'artist' => $artist->name,
            'image_url' => $song->album->image_url,
            'query' => $artist->name,
        ];
    }
}
