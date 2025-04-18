<?php

namespace App\Services;

use App\Models\Party;
use App\Models\PartyMember;
use App\Models\Song;
use Illuminate\Support\Collection;
use SpotifyWebAPI\SpotifyWebAPIException;

class RequestCheckService
{
    public function __construct(protected Party $party, protected PartyMember $member)
    {
    }

    public function checkCollection(Collection $input): Collection
    {
        $output = [];
        $bulkResponse = $this->bulkCheck($input);
        foreach ($input as $spotifyData) {
            $response = $this->singleCheck($spotifyData);
            if ($response) {
                $output[$spotifyData->id] = $response;
            } elseif (isset($bulkResponse[$spotifyData->id])) {
                $output[$spotifyData->id] = $bulkResponse[$spotifyData->id];
            } else {
                $output[$spotifyData->id] = new RequestCheckResponse(true);
            }
        }

        return collect($output);
    }

    public function checkSong(object|string $input): RequestCheckResponse
    {
        if (is_string($input)) {
            // Get the song from Spotify
            try {
                $spotifyData = $this->party->user->getSpotifyApi()->getTrack($input);
            } catch (SpotifyWebAPIException $ex) {
                if ($ex->getMessage() === 'invalid id') {
                    return new RequestCheckResponse(false, 'Song is not found in Spotify');
                }
                throw $ex;
            }
        } else {
            $spotifyData = $input;
        }

        $response = $this->singleCheck($spotifyData);
        if ($response) {
            return $response;
        }
        $response = $this->bulkCheck(collect([$spotifyData]));
        if ($response->count() > 0) {
            return $response->first();
        }

        return new RequestCheckResponse(true);
    }

    protected function bulkCheck(Collection $input): Collection
    {
        $output = [];

        // Get our Spotify IDs
        $spotifyIds = $input->pluck('id');

        // Get songs for these Spotify IDs
        $songs = Song::whereIn('spotify_id', $spotifyIds)->get();
        $indexedSongs = [];
        foreach ($songs as $song) {
            $indexedSongs[$song->id] = $song;
        }
        $songIds = array_keys($indexedSongs);

        // Find any songs played in the last X minutes
        if ($this->party->no_repeat_interval) {
            $cutoff = now()->subSeconds($this->party->no_repeat_interval);
            $history = $this->party->history()->where('played_at', '>=', $cutoff)->whereIn('song_id', $songIds)->get();
            foreach ($history as $playedSong) {
                $index = $indexedSongs[$playedSong->song_id]->spotify_id;
                $output[$index] = new RequestCheckResponse(false, 'This song has been played recently');
            }
        }

        $next = $this->party->next();
        if ($next && array_key_exists($next->song_id, $indexedSongs)) {
            $index = $indexedSongs[$next->song_id]->spotify_id;
            if (!isset($output[$index])) {
                $output[$index] = new RequestCheckResponse(false, 'This song will be played next');
            }
        }

        return collect($output);
    }

    protected function singleCheck(object $spotifyData): ?RequestCheckResponse
    {
        if (!$this->party->allow_requests) {
            return new RequestCheckResponse(false, 'Party is not accepting requests');
        }

        if ($this->member->role->code === 'banned') {
            return new RequestCheckResponse(false, 'You are not allowed to make requests');
        }

        if ($this->party->min_song_length && ($spotifyData->duration_ms / 1000) < $this->party->min_song_length) {
            return new RequestCheckResponse(false, 'Song is too short');
        }
        if ($this->party->max_song_length && ($spotifyData->duration_ms / 1000) > $this->party->max_song_length) {
            return new RequestCheckResponse(false, 'Song is too long');
        }
        if (!$this->party->explicit && $spotifyData->explicit) {
            return new RequestCheckResponse(false, 'Explicit songs are not allowed');
        }

        return null;
    }
}
