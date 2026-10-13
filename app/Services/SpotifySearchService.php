<?php

namespace App\Services;

use App\Models\Party;
use App\Models\PartyMember;
use App\Models\Vote;
use App\Services\Spotify\SpotifyGateway;
use App\Support\SpotifyPayload;
use Illuminate\Pagination\LengthAwarePaginator;
use stdClass;

class SpotifySearchService
{
    public function __construct(protected Party $party, protected PartyMember $member) {}

    /**
     * @return LengthAwarePaginator<int, stdClass>
     */
    public function search(string $query, int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        $options = [
            'market' => $this->party->user->market,
            'limit' => $perPage,
            'offset' => ($page - 1) * $perPage,
        ];

        $api = app(SpotifyGateway::class)->searchClient($this->party->user);
        $result = SpotifyPayload::object($api->search($query, 'track', $options));

        $tracks = SpotifyPayload::object($result->tracks);
        $items = $this->augmentResults(array_values(array_map(SpotifyPayload::object(...), $tracks->items)));

        return new LengthAwarePaginator($items, $tracks->total, $perPage, $page);
    }

    /**
     * @param  list<stdClass>  $items
     * @return list<stdClass>
     */
    protected function augmentResults(array $items): array
    {
        $ids = array_map(fn (stdClass $track) => $track->id, $items);

        $augmented = [];

        // Augmentation by upcoming
        if ($ids) {
            $upcomingSongs = $this->party->upcoming()->with(['song', 'user'])->whereNull('queued_at')->whereHas('song', function ($query) use ($ids) {
                $query->whereIn('spotify_id', $ids);
            })->get();

            $votes = [];
            $upcomingIds = $upcomingSongs->pluck('id');
            if ($upcomingIds->isNotEmpty()) {
                $allVotes = Vote::whereUserId($this->member->user->id)->whereIn('upcoming_song_id', $upcomingIds)->get();
                foreach ($allVotes as $vote) {
                    $votes[$vote->upcoming_song_id] = $vote;
                }
            }

            foreach ($upcomingSongs as $ucSong) {
                $augmented[$ucSong->song->spotify_id] = (object) [
                    'score' => $ucSong->score,
                    'vote' => $votes[$ucSong->id]->value ?? null,
                    'upcoming_id' => $ucSong->id,
                    'user' => $ucSong->user->nickname ?? null,
                ];
            }
        }

        $service = new RequestCheckService($this->party, $this->member);
        $checkResponses = $service->checkCollection(collect($items));

        foreach ($items as $item) {
            if (array_key_exists($item->id, $augmented)) {
                $item->upcoming = true;
                $item->upcoming_id = $augmented[$item->id]->upcoming_id;
                $item->score = $augmented[$item->id]->score;
                $item->vote = $augmented[$item->id]->vote;
                $item->user = $augmented[$item->id]->user;
            } else {
                $item->upcoming = false;
                $item->upcoming_id = null;
                $item->score = 0;
                $item->vote = null;
                $item->user = null;
            }

            $item->allowed = $checkResponses[$item->id]->allowed ?? true;
            $item->reason = $checkResponses[$item->id]->reason ?? null;
        }

        return $items;
    }
}
