<?php

namespace App\Services;

use App\Models\Party;
use App\Models\PartyMember;
use App\Models\Vote;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use SpotifyWebAPI\Request;
use SpotifyWebAPI\Session;
use SpotifyWebAPI\SpotifyWebAPI;

class SpotifySearchService
{
    protected ?Session $session = null;
    protected ?SpotifyWebAPI $api = null;

    public function __construct(protected Party $party, protected PartyMember $member)
    {
    }

    protected function getApi()
    {
        if ($this->api !== null) {
            return $this->api;
        }

        $account = $this->party->user->accounts()->whereHas('provider', function ($query) {
            $query->whereCode('spotifysearch');
        })->first();
        if (!$account) {
            Log::warning("{$this->party->user}: No Spotify Search account, using control account");
            $this->api = $this->party->user->getSpotifyApi();
            return $this->api;
        }

        if ($this->session === null) {
            $clientId = $account->provider->getSetting('client_id');
            $clientSecret = $account->provider->getSetting('client_secret');
            $this->session = new Session($clientId, $clientSecret);
            $this->session->setAccessToken($account->access_token);
        }

        // Create new API
        Log::debug("{$this->party->user}: Creating new Search API connection");
        $request = new Request();
        $this->api = new SpotifyWebAPI([], $this->session, $request);


        if ($account->access_token_expires_at < now()->addMinutes(5)) {
            Log::debug("{$this->party->user}: Refreshing expiring Search API access token");
            $this->session->refreshAccessToken($account->refresh_token);
            $account->access_token = $this->session->getAccessToken();
            $account->access_token_expires_at = new Carbon($this->session->getTokenExpiration());
            $account->save();
        }

        return $this->api;
    }

    public function search(string $query, int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        $options = [
            'market' => $this->party->user->market,
            'limit' => $perPage,
            'offset' => ($page - 1) * $perPage,
        ];

        $api = $this->getApi();
        $result = $api->search($query, 'track', $options);

        $tracks = $this->augmentResults($result->tracks);

        return new \Illuminate\Pagination\LengthAwarePaginator($tracks->items, $tracks->total, $perPage, $page);
    }

    protected function augmentResults(object $results): object
    {
        $ids = array_map(function ($track) {
            return $track->id;
        }, $results->items);

        $augmented = [];

        // Augmentation by upcoming
        if ($ids) {
            $upcomingSongs = $this->party->upcoming()->with(['song', 'user'])->whereNull('queued_at')->whereHas('song', function ($query) use ($ids) {
                $query->whereIn('spotify_id', $ids);
            })->get();

            $votes = [];
            $upcomingIds = $upcomingSongs->pluck('id');
            if ($upcomingIds) {
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
        $checkResponses = $service->checkCollection(collect($results->items));

        foreach ($results->items as $item) {
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

        return $results;
    }
}
