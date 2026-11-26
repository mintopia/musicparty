<?php

namespace App\Mods\AiRequestReview;

use App\Models\Party;
use App\Models\PlayedSong;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @phpstan-type Vibe array{text: string, built_at: string, stats: array{tracks_played: int, running_minutes: int}, recent: list<string>, top_rated: list<string>, downvoted: list<string>, artists: list<string>}
 */
class PartyVibeSummary
{
    public const RECENT = 15;

    public const TOP_RATED = 10;

    public const DOWNVOTED = 5;

    public const ARTISTS = 8;

    public const MAX_TITLE = 60;

    public const MAX_CHARS = 2000;

    public const TTL_SECONDS = 3600;

    public static function cacheKey(Party $party): string
    {
        return "party-vibe:{$party->id}";
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
        $artists = $this->dominantArtists($party);

        $aggregate = DB::table('played_songs')->where('party_id', $party->id)->selectRaw('count(*) as tracks, min(played_at) as first_at, max(played_at) as last_at')->first();
        $tracks = (int) ($aggregate->tracks ?? 0);
        $minutes = $aggregate !== null && $tracks > 0 ? (int) Carbon::parse($aggregate->first_at)->diffInMinutes(Carbon::parse($aggregate->last_at), true) : 0;

        $lines = ["Tracks played: {$tracks}", "Party running time: {$minutes} minutes"];
        foreach ([
            'Most recently played (newest first)' => $recent,
            'Highest rated by guests' => $topRated,
            'Most downvoted by guests' => $downvoted,
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

        return array_values($query->limit($limit)->get()->map(function (PlayedSong $played): string {
            $artists = $played->song->artists->pluck('name')->implode(', ');

            return Str::limit($played->song->name.($artists !== '' ? " - {$artists}" : ''), self::MAX_TITLE - 3, '...');
        })->all());
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
