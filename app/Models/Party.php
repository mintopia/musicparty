<?php

namespace App\Models;

use App\Events\SpotifyStatusUpdatedEvent;
use App\Jobs\PartyUpdate;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
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
        'song_started_at' => 'datetime',
        'state_updated_at' => 'datetime',
    ];

    public $noUpdate = false;

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

    public function next()
    {
        return $this->upcoming()
            ->whereNotNull('queued_at')
            ->orderBy('queued_at', 'DESC')
            ->first();
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
        while ($this->code === '') {
            $code = '';
            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $start = mt_rand(0, strlen(self::CODE_CHARSET) - 1);
                $code .= substr(self::CODE_CHARSET, $start, 1);
            }
            if (Party::whereCode($code)->count() === 0) {
                $this->code = $code;
            }
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
        $cutoff = Carbon::now()->subSeconds(2);
        if ($this->state_updated_at >= $cutoff) {
            Log::debug("[Party:{$this->id}] Already updated state recently");
            $this->noUpdate = true;
            return $this;
        }
        $this->noUpdate = false;
        $songChanged = $this->updateCurrentSong();
        if ($songChanged) {
            Log::info("[Party:{$this->id}] Song has changed, updating history, playlist and backfilling");
            $playlist = $this->updateHistory();
            $this->updatePlaylist($playlist);
            $this->backfillUpcomingSongs();
            $this->state_updated_at = Carbon::now();
        } else {
            Log::info("[Party:{$this->id}] Currently playing song has not changed, no further updates");
        }
        $this->save();
        Log::info("[Party:{$this->id}] Finished updating state");
        return $this;
    }

    public function getNextUpdateDelay(): ?int
    {
        if ($this->noUpdate) {
            return null;
        }

        if (!$this->song) {
            return 60;
        }

        if (!$this->user->status || !$this->user->status->is_playing) {
            return 60;
        }

        $remaining = ($this->song->length - $this->user->status->progress_ms) / 1000;
        if ($remaining > 60) {
            return 60;
        }
        return max(5, floor($remaining / 2));
    }

    protected function updateCurrentSong()
    {
        Log::debug("[Party:{$this->id}] Updating current song");
        $current = $this->user->getSpotifyStatus();
        if ($this->song_id && (!$current || !property_exists($current, 'item') || !$current->item)) {
            Log::info("[Party:{$this->id}] Not playing anything");
            $this->song_id = null;
            $this->song_started_at = null;
            $this->save();
            return true;
        } elseif ($current) {
            $song = Song::fromSpotify($current->item);
            if ($song->id != $this->song_id) {
                Log::info("[Party:{$this->id}] Updating current song to [{$song->spotify_id}] {$song->name}");
                $this->song()->associate($song);
                $this->song_started_at = Carbon::now()->subMilli($current->progress_ms);
                $this->save();
                return true;
            }
        }
        return false;
    }

    protected function updateHistory(): object
    {
        Log::debug("[Party:{$this->id}] Updating history");
        $playlist = $this->getPlaylist();

        // Our history, not always complete. Track IDs are relinked
        $options = [
            'limit' => 20,
            'market' => $this->user->market,
        ];
        $history = $this->user->getSpotifyApi()->getMyRecentTracks($options)->items;

        // Playlist MAY be relinked to playable tracks
        $relinked = [];
        foreach ($playlist->tracks->items as $plItem) {
            $key = "party.{$this->id}.relinked.{$plItem->track->id}";
            $relinked[$plItem->track->id] = Cache::remember($key, 86400, function() use ($plItem) {
                return $this->user->getSpotifyApi()->getTrack($plItem->track->id, [
                    'market' => $this->user->market,
                ]);
            });
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
                Log::info("[Party:{$this->id}] Removing [{$track->id}] {$track->name}");
                $song = Song::fromSpotify($track);
                $playedSong = new PlayedSong;
                $playedSong->party()->associate($this);
                $playedSong->song()->associate($song);
                $playedSong->played_at = new Carbon($track->played_at);
                $playedSong->save();
            }

            $playlist = $this->getPlaylist();
        }

        return $playlist;
    }

    protected function updatePlaylist(object $playlist)
    {
        Log::debug("[Party:{$this->id}] Updating playlist");
        $toAdd = self::PLAYLIST_LENGTH - $playlist->tracks->total;
        if ($toAdd < 1) {
            Log::debug("[Party:{$this->id}] No tracks to add");
            return;
        }

        $songsToAdd = $this->upcoming()
            ->whereNull('queued_at')
            ->orderBy('votes', 'DESC')
            ->orderBy('created_at', 'ASC')
            ->orderBy('id', 'ASC')
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
            Log::info("[Party:{$this->id}] Added [{$song->song->spotify_id}] {$song->song->name} to party playlist");
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

        $tracks = Cache::remember("party.{$this->id}.backupplaylist", 300, function() {
            return $this->getBackupPlaylistTracks();
        });

        $existingIds = $this->upcoming()
            ->where(function($query) {
                $query->whereNull('queued_at')
                    ->orWhere('queued_at', '>', Carbon::now()->subMinutes(30));})
            ->with('song')
            ->get()
            ->pluck('song.spotify_id')
            ->toArray();

        $tracks = array_filter($tracks, function ($track) use ($existingIds) {
            return !in_array($track->track->id, $existingIds);
        });

        $toAdd = min($toAdd, count($tracks));
        shuffle($tracks);

        for ($i = 0; $i < $toAdd; $i++) {
            $track = $tracks[$i];
            $song = Song::fromSpotify($track->track);
            Log::info("[Party:{$this->id}] Adding [{$song->spotify_id}] {$song->name} from backup playlist");
            $upcoming = new UpcomingSong;
            $upcoming->party()->associate($this);
            $upcoming->song()->associate($song);
            $upcoming->save();
        }
    }

    protected function getBackupPlaylistTracks(): array
    {
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
        return $tracks;
    }

    protected function getPlaylist(): object
    {
        return $this->user->getSpotifyApi()->getPlaylist($this->playlist_id);
    }

    public function getState(?User $user, bool $asOwner = false): array
    {
        if ($user && $user->id === $this->user_id) {
            $asOwner = true;
        }

        $next = $this->next();

        return [
            'code' => $this->code,
            'name' => $this->name,
            'backup_playlist_id' => $asOwner ? $this->backup_playlist_id : null,
            'status' => $this->getStatus($asOwner),
            'now' => $this->song ? (object) $this->song->toApi() : null,
            'next' => $next ? (object) $next->toApi() : null,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }

    protected function getStatus(bool $asOwner): object
    {
        $status = $this->user->status;

        if (!$status) {
            return (object)[
                'device' => null,
                'repeat' => null,
                'shuffle' => null,
                'active' => false,
                'is_playing' => false,
                'progress' => null,
                'length' => null,
                'updated_at' => Carbon::now()->toIso8601String(),
            ];
        }

        if ($asOwner) {
            $data = [
                'device' => (object)[
                    'id' => $status->device->id,
                    'name' => $status->device->name,
                    'type' => $status->device->type,
                    'volume' => $status->device->volume_percent,
                ],
                'repeat' => $status->repeat_state,
                'shuffle' => $status->shuffle_state,
            ];
        } else {
            $data = [
                'device' => null,
                'repeat' => null,
                'shuffle' => null,
            ];
        }

        if (property_exists($status, 'context') && property_exists($status->context, 'uri')) {
            $data['active'] = $status->context->uri == "spotify:playlist:{$this->playlist_id}";
        } else {
            $data['active'] = false;
        }

        if (!$this->song || $status->item->id != $this->song->spotify_id) {
            $data['is_playing'] = false;
            $data['progress'] = null;
            $data['length'] = null;
        } else {
            $data['is_playing'] = $status->is_playing;
            $data['progress'] = $status->progress_ms;
            $data['length'] = $this->song->length;
        }
        $data['updated_at'] = $this->user->status_updated_at->toIso8601String();

        return (object) $data;
    }
}
