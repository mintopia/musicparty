<?php
namespace App\Services;

use App\Models\Party;
use App\Services\SpotifyAPIRequest;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SpotifyWebAPI\Session;
use SpotifyWebAPI\SpotifyWebAPI;

class SpotifySearchService
{
    protected ?Session $session = null;
    protected ?SpotifyWebAPI $api = null;

    public function __construct(protected Party $party, protected User $user)
    {

    }

    protected function getApi()
    {
        if ($this->api !== null) {
            return $this->api;
        }

        if (!config('services.spotify_search.client_id') || !config('services.spotify_search.client_secret') || !config('services.spotify_search.refresh_token')) {
            Log::warning("[Party:{$this->party->id}] Using Music Party application, not specific search application!");
            return $this->party->user->getSpotifyApi();
        }

        $this->session = new Session(
            config('services.spotify_search.client_id'),
            config('services.spotify_search.client_secret'),
        );
        $refreshToken = config('services.spotify_search.refresh_token');
        $accessToken = Cache::get('spotify.search.access_token');

        $cutoff = Carbon::now()->addMinutes(5);
        if (!$accessToken || $accessToken->expiresAt < $cutoff) {
            Log::debug("Refreshing expiring search access token");
            $this->session->refreshAccessToken($refreshToken);
            $accessToken = (object) [
                'token' => $this->session->getAccessToken(),
                'expiresAt' => new Carbon($this->session->getTokenExpiration()),
            ];
            Cache::add('spotify.search.access_token', $accessToken);
        }
        $this->session->setAccessToken($accessToken->token);

        $request = new SpotifyAPIRequest();
        $this->api = new SpotifyWebAPI([], $this->session, $request);
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
        $result = $api->search($query, 'track,artist,album', $options);

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
                $voted = Vote::whereUserId($this->user->id)->whereIn('upcoming_song_id', $upcomingIds)->pluck('upcoming_song_id')->toArray();
            }

            foreach ($upcomingSongs as $ucSong) {
                $augmented[$ucSong->song->spotify_id] = (object) [
                    'votes' => $ucSong->votes,
                    'hasVoted' => in_array($ucSong->id, $voted),
                ];
            }
        }

        foreach ($results->items as $item) {
            if (array_key_exists($item->id, $augmented)) {
                $item->upcoming = true;
                $item->votes = $augmented[$item->id]->votes;
                $item->hasVoted = $augmented[$item->id]->hasVoted;
            } else {
                $item->upcoming = false;
                $item->votes = 0;
                $item->hasVoted = false;
            }
        }

        return $results;
    }
}
