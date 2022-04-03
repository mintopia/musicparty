<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * App\Models\Party
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $code
 * @property string $playlist_id
 * @property string|null $backup_playlist_id
 * @property string|null $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\PlayedSongs[] $played
 * @property-read int|null $played_count
 * @method static \Illuminate\Database\Eloquent\Builder|Party newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Party newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Party query()
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereBackupPlaylistId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Party wherePlaylistId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereUserId($value)
 * @mixin \Eloquent
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\UpcomingSong[] $upcoming
 * @property-read int|null $upcoming_count
 * @property-read \App\Models\User $user
 * @property \Illuminate\Support\Carbon|null $history_updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereHistoryUpdatedAt($value)
 * @property int|null $song_id
 * @property string|null $song_started_at
 * @property-read \App\Models\Song|null $song
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereSongId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereSongStartedAt($value)
 */
class Party extends Model
{
    const CODE_LENGTH = 4;
    const CODE_CHARSET = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    const MINIMUM_UPCOMING = 5;
    const PLAYLIST_LENGTH = 2;

    use HasFactory;

    protected $casts = [
        'status' => 'object',
        'history_updated_at' => 'datetime',
        'song_started_at' => 'datetime',
    ];

    public function getRouteKeyName()
    {
        return 'code';
    }

    public function played()
    {
        return $this->hasMany(PlayedSong::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function upcoming()
    {
        return $this->hasMany(UpcomingSong::class);
    }

    public function song()
    {
        return $this->belongsTo(Song::class);
    }

    public function setCode()
    {
        if ($this->code) {
            return;
        }

        $this->code = '';
        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $start = mt_rand(0, strlen(self::CODE_CHARSET) - 1);
            $this->code .= substr(self::CODE_CHARSET, $start, 1);
        }
    }

    public function fixPlaylist()
    {
        $playlist = $this->getPlaylist();
        $trackIds = collect($playlist->tracks->items)->pluck('track.id');

        $this->user->getSpotifyApi()->unfollowPlaylist($this->playlist_id);
        $this->playlist_id = null;
        $this->createPlaylist();
        $this->save();

        $this->user->getSpotifyApi()->addPlaylistTracks($this->playlist_id, $trackIds->toArray());
    }

    public function createPlaylist()
    {
        if ($this->playlist_id) {
            return;
        }

        $playlist = $this->user->getSpotifyApi()->createPlaylist([
            'name' => "Spotify Party - {$this->code}",
            'public' => true,
            'description' => 'Automatically managed Spotify Party playlist',
        ]);

        $this->playlist_id = $playlist->id;
    }

    public function updateState(): Party
    {
        Log::info("[Party:{$this->id}] Updating state");
        $this->updateCurrentSong();
        $this->updateHistory();
        $this->updatePlaylist();
        $this->backfillUpcomingSongs();
        return $this;
    }

    protected function updatePlaylist()
    {
        Log::debug("[Party:{$this->id}] Updating playlist");
        $playlist = $this->getPlaylist();
        $toAdd = self::PLAYLIST_LENGTH - $playlist->tracks->total;
        if ($toAdd < 1) {
            Log::debug("[Party:{$this->id}] No tracks to add");
            return;
        }

        $songsToAdd = $this->upcoming()
            ->whereNull('queued_at')
            ->orderBy('votes', 'DESC')
            ->orderBy('created_at', 'ASC')
            ->limit($toAdd)
            ->get();

        if ($songsToAdd->isEmpty()) {
            Log::debug("[Party:{$this->id}] Found no songs in the upcoming songs list to backfill");
            return;
        }

        $trackIds = [];
        foreach ($songsToAdd as $song) {
            $trackIds[] = $song->song->spotify_id;
        }
        $this->user->getSpotifyApi()->addPlaylistTracks($this->playlist_id, $trackIds);
        foreach ($songsToAdd as $song) {
            Log::debug("[Party:{$this->id}] Added [{$song->song->spotify_id}] {$song->song->name} from party playlist");
            $song->queued_at = Carbon::now();
            $song->save();
        }
    }

    protected function backfillUpcomingSongs()
    {
        Log::debug("[Party:{$this->id}] Backfilling upcoming songs");
        $toAdd = self::MINIMUM_UPCOMING - $this->upcoming()->whereNull('queued_at')->count();
        if ($toAdd <= 0) {
            return;
        }

        $tracks = [];
        $offset = 0;
        do {
            $response = $this->user->getSpotifyApi()->getPlaylistTracks($this->backup_playlist_id, [
                'limit' => 50,
                'offset' => $offset,
                'market' => $this->user->market,
           ]);
            $tracks = array_merge($tracks, $response->items);
            $offset += 50;
        } while ($response->next !== null);

        for ($i = 0; $i < $toAdd; $i++) {
            $index = mt_rand(0, count($tracks) - 1);
            $track = $tracks[$index];
            $song = Song::fromSpotify($track->track);
            Log::debug("[Party:{$this->id}] Adding [{$song->spotify_id}] {$song->name} from backup playlist");
            $upcoming = new UpcomingSong;
            $upcoming->party()->associate($this);
            $upcoming->song()->associate($song);
            $upcoming->save();
        }
    }

    protected function getPlaylist(): object
    {
        return $this->user->getSpotifyApi()->getPlaylist($this->playlist_id);
    }

    protected function updateCurrentSong(): Party
    {
        Log::debug("[Party:{$this->id}] Updating current song");
        $current = $this->user->getSpotifyStatus();
        if ($this->song_id && (!$current || !property_exists($current, 'item') || !$current->item)) {
            Log::info("[Party:{$this->id}] Not playing anything");
            $this->song_id = null;
            $this->song_started_at = null;
            $this->save();
            return $this;
        } elseif ($current) {
            $song = Song::fromSpotify($current->item);
            if ($song->id != $this->song_id) {
                Log::info("[Party:{$this->id}] Updating current song to [{$song->spotify_id}] {$song->name}");
                $this->song()->associate($song);
                $this->song_started_at = Carbon::now()->subMilli($current->progress_ms);
                $this->save();
            }
        }
        return $this;
    }

    protected function updateHistory(): Party
    {
        Log::debug("[Party:{$this->id}] Updating history");

        // Our history, not always complete. Track IDs are relinked
        $options = [
            'limit' => 20,
            'market' => 'GB',
        ];
        $history = $this->user->getSpotifyApi()->getMyRecentTracks($options)->items;
        $this->history_updated_at = Carbon::now();
        $this->save();

        // Playlist MAY be relinked to playable tracks
        $playlist = $this->getPlaylist();
        $relinked = [];
        foreach ($playlist->tracks->items as $index => $plItem) {
            $relinked[$plItem->track->id] = $this->user->getSpotifyApi()->getTrack($plItem->track->id, [
                'market' => $this->user->market,
            ]);
        }

        // If playlist entry is in history, remove it from playlist
        // if not currently playing, do nothing
        // If current playing is not in playlist, do nothing
        // If current playing is first in playlist - good

        $toRemove = [];
        $previous = [];

        foreach ($relinked as $plItem) {
            $plItem->played_at = Carbon::now()->subMilliseconds($plItem->duration_ms);
            foreach ($history as $hItem) {
                if ($hItem->track->id === $plItem->id) {
                    // Playlist entry is in history, remove it and we're done
                    $plItem->played_at = $hItem->played_at;
                    $toRemove[] = $plItem;
                    continue 2;
                }
            }

            $previous[] = $plItem;

            // If current playing is in playlist and not first, remove previous items
            if (count($previous) > 1 && $this->song && $this->song->spotify_id == $plItem->id) {
                Log::debug("[Party:{$this->id}] Found playlist item in history that isn't first item - removing prior");
                $toRemove = array_merge($toRemove, array_slice($previous, 0, -1));
            }
        }

        if ($toRemove) {
            $idsToRemove = [
                'tracks' => array_map(function ($entry) {
                    return ['uri' => $entry->id];
                }, $toRemove),
            ];
            $this->user->getSpotifyApi()->deletePlaylistTracks($this->playlist_id, $idsToRemove);

            foreach ($toRemove as $track) {
                Log::debug("[Party:{$this->id}] Removing [{$track->id}] {$track->name}");
                $song = Song::fromSpotify($track);
                $playedSong = new PlayedSong;
                $playedSong->party()->associate($this);
                $playedSong->song()->associate($song);
                $playedSong->played_at = new Carbon($track->played_at);
                $playedSong->save();
            }
        }

        return $this;
    }
}
