<?php

namespace App\Mods\AiRequestReview;

use App\Models\Party;
use App\Models\PlayedSong;
use App\Models\Song;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @phpstan-type Vibe array{text: string, built_at: string, stats: array{tracks_played: int, running_minutes: int}, recent: list<array{title: string, artist: string}>, top_rated: list<array{title: string, artist: string}>, downvoted: list<array{title: string, artist: string}>, most_upvoted_requests: list<array{title: string, artist: string}>, most_downvoted_requests: list<array{title: string, artist: string}>, artists: list<array{name: string, plays: int}>}
 */
class PartyVibeSummary
{
    public const RECENT = 15;

    public const TOP_RATED = 10;

    public const DOWNVOTED = 5;

    public const VOTED_REQUESTS = 5;

    public const ARTISTS = 8;

    public const MAX_CHARS = 2000;

    public const TTL_SECONDS = 3600;

    public static function cacheKey(Party $party): string
    {
        return "party-vibe:v3:{$party->id}";
    }

    public function text(Party $party): string
    {
        return $this->data($party)['text'];
    }

    /**
     * @return Vibe
     */
    public function data(Party $party): array
    {
        /** @var Vibe */
        return Cache::remember(self::cacheKey($party), self::TTL_SECONDS, fn (): array => $this->build($party));
    }

    public function refresh(Party $party): void
    {
        Cache::put(self::cacheKey($party), $this->build($party), self::TTL_SECONDS);
    }

    public function forget(Party $party): void
    {
        Cache::forget(self::cacheKey($party));
    }

    /**
     * @return Vibe
     */
    public function build(Party $party): array
    {
        $recent = $this->titles($party, fn (Builder $query) => $query->orderByDesc('played_at')->orderByDesc('id'), self::RECENT);
        $topRated = $this->titles($party, fn (Builder $query) => $query->where('rating', '>', 0)->orderByDesc('rating')->orderByDesc('played_at'), self::TOP_RATED);
        $downvoted = $this->titles($party, fn (Builder $query) => $query->where('rating', '<', 0)->orderBy('rating')->orderByDesc('played_at'), self::DOWNVOTED);
        $mostUpvoted = $this->votedRequests($party, true);
        $mostDownvoted = $this->votedRequests($party, false);
        $artists = $this->dominantArtists($party);

        $aggregate = DB::table('played_songs')->where('party_id', $party->id)->selectRaw('count(*) as tracks, min(played_at) as first_at, max(played_at) as last_at')->first();
        $tracks = (int) ($aggregate->tracks ?? 0);
        $minutes = $aggregate !== null && $tracks > 0 ? (int) Carbon::parse($aggregate->first_at)->diffInMinutes(Carbon::parse($aggregate->last_at), true) : 0;

        $lines = ["Tracks played: {$tracks}", "Party running time: {$minutes} minutes"];
        foreach ([
            'Most recently played (newest first)' => $recent,
            'Highest rated by guests' => $topRated,
            'Most downvoted by guests' => $downvoted,
            'Most upvoted requests' => $mostUpvoted,
            'Most downvoted requests' => $mostDownvoted,
        ] as $heading => $entries) {
            if ($entries !== []) {
                $lines[] = "{$heading}: ".implode('; ', array_map($this->label(...), $entries));
            }
        }
        if ($artists !== []) {
            $lines[] = 'Most played artists: '.implode('; ', array_map(fn (array $artist): string => "{$artist['name']} ({$artist['plays']} plays)", $artists));
        }

        return [
            'text' => Str::limit(implode("\n", $lines), self::MAX_CHARS - 3, '...'),
            'built_at' => now()->toIso8601String(),
            'stats' => ['tracks_played' => $tracks, 'running_minutes' => $minutes],
            'recent' => $recent,
            'top_rated' => $topRated,
            'downvoted' => $downvoted,
            'most_upvoted_requests' => $mostUpvoted,
            'most_downvoted_requests' => $mostDownvoted,
            'artists' => $artists,
        ];
    }

    /**
     * @param  callable(Builder<PlayedSong>): mixed  $scope
     * @return list<array{title: string, artist: string}>
     */
    protected function titles(Party $party, callable $scope, int $limit): array
    {
        $query = PlayedSong::query()->where('party_id', $party->id)->with('song.artists:id,name');
        $scope($query);

        return array_values($query->limit($limit)->get()->map(fn (PlayedSong $played): array => $this->entry($played->song))->all());
    }

    /**
     * @return array{title: string, artist: string}
     */
    protected function entry(Song $song): array
    {
        return ['title' => $song->name, 'artist' => $song->artists->pluck('name')->implode(', ')];
    }

    /**
     * @param  array{title: string, artist: string}  $track
     */
    protected function label(array $track): string
    {
        return $track['title'].($track['artist'] !== '' ? " - {$track['artist']}" : '');
    }

    /**
     * @return list<array{title: string, artist: string}>
     */
    protected function votedRequests(Party $party, bool $up): array
    {
        $rows = DB::table('votes')
            ->join('upcoming_songs', 'upcoming_songs.id', '=', 'votes.upcoming_song_id')
            ->where('upcoming_songs.party_id', $party->id)
            ->whereNull('upcoming_songs.flagged_at')
            ->groupBy('upcoming_songs.song_id')
            ->havingRaw($up ? 'sum(votes.value) > 0' : 'sum(votes.value) < 0')
            ->orderByRaw($up ? 'sum(votes.value) desc' : 'sum(votes.value) asc')
            ->orderBy('upcoming_songs.song_id')
            ->limit(self::VOTED_REQUESTS)
            ->pluck('upcoming_songs.song_id');
        $songs = Song::query()->whereIn('id', $rows)->with('artists:id,name')->get()->keyBy('id');

        return array_values($rows->map(fn (mixed $id): ?array => ($song = $songs->get($id)) instanceof Song ? $this->entry($song) : null)->filter()->all());
    }

    /**
     * @return list<array{name: string, plays: int}>
     */
    protected function dominantArtists(Party $party): array
    {
        $rows = DB::table('played_songs')
            ->join('artist_song', 'artist_song.song_id', '=', 'played_songs.song_id')
            ->join('artists', 'artists.id', '=', 'artist_song.artist_id')
            ->where('played_songs.party_id', $party->id)
            ->groupBy('artists.id', 'artists.name')
            ->orderByRaw('count(*) desc')
            ->orderBy('artists.name')
            ->limit(self::ARTISTS)
            ->selectRaw('artists.name as name, count(*) as plays')
            ->get();

        return array_values($rows->map(fn (object $row): array => ['name' => (string) $row->name, 'plays' => (int) $row->plays])->all());
    }
}
