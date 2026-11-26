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
 * @phpstan-type Vibe array{text: string, built_at: string, stats: array{tracks_played: int, running_minutes: int}, recent: list<string>, top_rated: list<string>, downvoted: list<string>, most_upvoted_requests: list<string>, most_downvoted_requests: list<string>, artists: list<string>}
 */
class PartyVibeSummary
{
    public const RECENT = 15;

    public const TOP_RATED = 10;

    public const DOWNVOTED = 5;

    public const VOTED_REQUESTS = 5;

    public const ARTISTS = 8;

    public const MAX_TITLE = 60;

    public const MAX_CHARS = 2000;

    public const TTL_SECONDS = 3600;

    public static function cacheKey(Party $party): string
    {
        return "party-vibe:v2:{$party->id}";
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
            'Most played artists' => $artists,
        ] as $heading => $items) {
            if ($items !== []) {
                $lines[] = "{$heading}: ".implode('; ', $items);
            }
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
     * @return list<string>
     */
    protected function titles(Party $party, callable $scope, int $limit): array
    {
        $query = PlayedSong::query()->where('party_id', $party->id)->with('song.artists:id,name');
        $scope($query);

        return array_values($query->limit($limit)->get()->map(fn (PlayedSong $played): string => $this->label($played->song))->all());
    }

    protected function label(Song $song): string
    {
        $artists = $song->artists->pluck('name')->implode(', ');

        return Str::limit($song->name.($artists !== '' ? " - {$artists}" : ''), self::MAX_TITLE - 3, '...');
    }

    /**
     * @return list<string>
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

        return array_values($rows->map(fn (mixed $id): ?string => ($song = $songs->get($id)) instanceof Song ? $this->label($song) : null)->filter()->all());
    }

    /**
     * @return list<string>
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

        return array_values($rows->map(fn (object $row): string => Str::limit((string) $row->name, self::MAX_TITLE - 3, '...')." ({$row->plays})")->all());
    }
}
