<?php

namespace App\Services\Search;

use App\Models\Party;
use App\Models\PartySearch;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PartySearchSuggestions
{
    private const int TONIGHT_HOURS = 12;

    public function record(Party $party, ?User $user, string $query): void
    {
        $query = mb_substr(trim($query), 0, 120);
        if ($query === '') {
            return;
        }

        PartySearch::create([
            'party_id' => $party->id,
            'user_id' => $user?->id,
            'query' => $query,
            'term' => mb_strtolower($query),
        ]);
    }

    /**
     * @return list<string>
     */
    public function recent(Party $party, int $limit): array
    {
        $latest = PartySearch::query()
            ->where('party_id', $party->id)
            ->groupBy('term')
            ->selectRaw('max(id) as id')
            ->orderByDesc('id')
            ->limit($limit)
            ->pluck('id');

        return array_values(PartySearch::query()->whereIn('id', $latest)->orderByDesc('id')->get()
            ->map(fn (PartySearch $search): string => $search->query)->all());
    }

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

        return array_values($rows->map(fn (object $row): array => [
            'id' => (string) $row->id,
            'name' => (string) $row->name,
            'image_url' => $this->coverFor($party, (string) $row->id),
            'requests' => (int) $row->requests,
        ])->all());
    }

    /**
     * The cover of the artist's most recently requested track at this party, standing in for an artist photo.
     */
    protected function coverFor(Party $party, string $providerArtistId): ?string
    {
        $url = DB::table('upcoming_songs')
            ->join('songs', 'songs.id', '=', 'upcoming_songs.song_id')
            ->join('albums', 'albums.id', '=', 'songs.album_id')
            ->join('artist_song', 'artist_song.song_id', '=', 'upcoming_songs.song_id')
            ->join('artists', 'artists.id', '=', 'artist_song.artist_id')
            ->where('upcoming_songs.party_id', $party->id)
            ->where('artists.provider_artist_id', $providerArtistId)
            ->orderByDesc('upcoming_songs.id')
            ->value('albums.image_url');

        return $url === null || $url === '' ? null : (string) $url;
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
