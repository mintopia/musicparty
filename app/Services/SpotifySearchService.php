<?php
namespace App\Services;

use App\Models\Party;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SpotifySearchService
{
    public function __construct(protected Party $party, protected User $user)
    {

    }

    public function search(string $query, int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        $options = [
            'market' => $this->party->user->market,
            'limit' => $perPage,
            'offset' => ($page - 1) * $perPage,
        ];

        $result = $this->party->user->getSpotifyApi()->search($query, 'track,artist,album', $options);

        $tracks = $this->augmentResults($result->tracks);

        return new \Illuminate\Pagination\LengthAwarePaginator($tracks->items, $tracks->total, $perPage, $page);
    }

    protected function augmentResults(object $results): object
    {
        $ids = array_map(function ($track) {
            return $track->id;
        }, $results->items);

        $augmented = [];
        if ($ids) {
            $upcomingSongs = $this->party->upcoming()->with('song')->whereNull('queued_at')->whereHas('song', function ($query) use ($ids) {
                $query->whereIn('spotify_id', $ids);
            })->get();

            $voted = [];
            $upcomingIds = $upcomingSongs->pluck('id');
            if ($upcomingIds) {
                $voted = Vote::whereUserId($this->user->id)->whereIn('upcoming_song_id', $upcomingIds)->pluck('id')->toArray();
            }

            foreach ($upcomingSongs as $ucSong) {
                $augmented[$ucSong->song->spotify_id] = (object) [
                    'votes' => $ucSong->votes,
                    'hasVoted' => array_key_exists($ucSong->id, $voted),
                ];
            }
        }

        foreach ($results->items as $item) {
            if (array_key_exists($item->id, $augmented)) {
                $item->votes = $augmented[$item->id]->votes;
                $item->hasVoted = $augmented[$item->id]->hasVoted;
            } else {
                $item->votes = 0;
                $item->hasVoted = false;
            }
        }

        return $results;
    }
}
