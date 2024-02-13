<?php

namespace App\Models;

use App\Models\Traits\ToString;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * @mixin IdeHelperParty
 */
class Party extends Model
{
    use HasFactory, ToString;

    const MINIMUM_UPCOMING = 5;
    const PLAYLIST_LENGTH = 2;

    protected $attributes = [
        'allow_requests' => true,
        'explicit' => true,
        'downvotes' => true,
        'process_requests' => true,
    ];

    protected $casts = [
        'last_updated_at' => 'datetime',
        'song_started_at' => 'datetime',
    ];

    public function toStringName(): string
    {
        return $this->code;
    }

    public function getRouteKeyName()
    {
        return 'code';
    }

    public function members(): HasMany
    {
        return $this->hasMany(PartyMember::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function upcoming(): HasMany
    {
        return $this->hasMany(UpcomingSong::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(PlayedSong::class);
    }

    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }

    public function resolveChildRouteBinding($childType, $value, $field)
    {
        switch ($childType) {
            case 'upcomingsong':
            case 'song':
                return $this->upcoming()->whereId($value)->with(['song', 'user'])->first();

            case 'user':
                return $this->members()->whereId($value)->with(['user', 'role'])->first();

            default:
                return parent::resolveChildRouteBinding($childType, $value, $field);
        }
    }

    public function next()
    {
        return $this->upcoming()
            ->whereNotNull('queued_at')
            ->orderBy('queued_at', 'DESC')
            ->first();
    }

    public function updateState(): Party
    {
        Log::info("{$this}: Updating state");
        $cutoff = Carbon::now()->subSeconds(2);
        if ($this->last_updated_at >= $cutoff) {
            Log::debug("{$this}: Already updated state recently");
            return $this;
        }

        if (!$this->active) {
            Log::debug("{$this}: Party is not active");
            // Still update current song, in case they're using it manually and still want it reflected on screen
            $this->updateCurrentSong();
            $this->updateHistory();
            return $this;
        }

        $this->updateCurrentSong();
        $playlist = $this->updateHistory();
        $this->syncPlaylist($playlist);
        $this->backfillUpcomingSongs();
        $this->last_updated_at = Carbon::now();
        $this->save();
        Log::info("{$this}: Finished updating state");
        return $this;
    }

    public function getState(): object
    {
        $next = $this->next();
        return (object)[
            'code' => $this->code,
            'name' => $this->name,
            'backup_playlist_id' => $this->backup_playlist_id,
            'status' => $this->getStatus(),
            'now' => $this->song?->toApi(),
            'next' => $next?->toApi(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }

    protected function getStatus(): object
    {
        $status = $this->user->status;

        if (!$status) {
            return (object)[
                'device' => null,
                'repeat' => null,
                'shuffle' => null,
                'active' => false,
                'isPlaying' => false,
                'progress' => null,
                'length' => null,
                'updatedAt' => Carbon::now()->toIso8601String(),
            ];
        }

        $data = (object)[
            'device' => (object)[
                'id' => $status->device->id,
                'name' => $status->device->name,
                'type' => $status->device->type,
                'volume' => $status->device->volume_percent,
            ],
            'repeat' => $status->repeat_state,
            'shuffle' => $status->shuffle_state,
            'active' => false,
            'isPlaying' => $status->is_playing,
            'progress' => null,
            'length' => null,
            'updatedAt' => $this->user->status_updated_at->toIso8601String(),
        ];

        // Active if our current context is the playlist
        if ($status->context->uri ?? null) {
            $data->active = $status->context->uri === "spotify:playlist:{$this->playlist_id}";
        }

        if ($this->song && $status->item->id == $this->song->spotify_id) {
            $data->active = true;
            $data->progress = $status->progress_ms;
            $data->length = $this->song->length;
        }

        return $data;
    }

    public function play(): void
    {
        Log::debug("{$this}: Spotify API -> play()");
        $this->user->getSpotifyApi()->play($this->recent_device_id, ['foo' => 'bar']);
    }

    public function pause(): void
    {
        Log::debug("{$this}: Spotify API -> pause()");
        $this->user->getSpotifyApi()->pause($this->recent_device_id);
    }

    public function nextTrack(): void
    {
        Log::debug("{$this}: Spotify API -> next()");
        $this->user->getSpotifyApi()->next($this->recent_device_id);
    }

    public function previousTrack(): void
    {
        Log::debug("{$this}: Spotify API -> seek()");
        $this->user->getSpotifyApi()->seek([
            'position_ms' => 0,
            'device_id' => $this->recent_device_id,
        ]);
    }


    protected function getPlaylist(): object
    {
        return $this->user->getPlaylist($this->playlist_id);
    }

    public function isPlaylistBroken(?string $playlistUri = null): bool
    {
        // If we aren't active, we can't be broken
        if (!$this->active) {
            return false;
        }

        $this->user->getSpotifyStatus();

        // If we're playing something, we can't be broken
        if ($this->user->status && $this->user->status->is_playing) {
            return false;
        }

        if ($playlistUri === null) {
            $playlistUri = "spotify:playlist:{$this->playlist_id}";
        }

        // We might be broken, check our track history
        $recentTracks = $this->user->getRecentTracks();
        if (!$recentTracks->items) {
            return false;
        }

        // Not playing in the context of our playlist
        if ($recentTracks->items[0]->context->uri !== $playlistUri) {
            return false;
        }

        return true;
    }

    public function fixPlaylist(bool $force = false)
    {
        if (!$force && !$this->isPlaylistBroken()) {
            return;
        }

        $playlist = $this->getPlaylist();
        $trackIds = collect($playlist->tracks->items)->pluck('track.id');

        $this->user->getSpotifyStatus();
        $api = $this->user->getSpotifyApi();

        Log::debug("{$this}: Spotify API -> unfollowPlaylist({$this->playlist_id})");
        $api->unfollowPlaylist($this->playlist_id);
        $oldPlaylistUri = "spotify:playlist:{$this->playlist_id}";
        $this->playlist_id = null;
        $this->updatePlaylist();
        $this->save();

        Log::debug("{$this}: Spotify API -> addPlaylistTracks({$this->playlist_id}, [...])");
        $api->addPlaylistTracks($this->playlist_id, $trackIds->toArray());
        $trackToPlay = $this->song->spotify_id ?? $this->next()->song->spotify_id;

        $forcePlayback = $force || $this->shouldForcePlayback($oldPlaylistUri);

        if ($forcePlayback ) {
            Log::debug("{$this}: Spotify API -> play({$this->playlist_id}, [...])");
            $api->play($this->recent_device_id, [
                'context_uri' => "spotify:playlist:{$this->playlist_id}",
                'offset' => [
                    'uri' => "spotify:track:{$trackToPlay}",
                ],
            ]);
        }
    }

    protected function shouldForcePlayback(?string $oldPlaylistUri): bool
    {
        // First scenario - we are playing inside our current playlist, we want to restart playback
        $currentPlaylistUri = $this->user->status->context->uri ?? null;
        if ($currentPlaylistUri && $currentPlaylistUri === $oldPlaylistUri) {
            return true;
        }

        // Second scenario - the last thing we played was current playlist and we have stopped
        return $this->isPlaylistBroken($oldPlaylistUri);
    }


    protected function updateHistory(): object
    {
        Log::debug("{$this} Updating history");
        $playlist = $this->getPlaylist();

        // Our history, not always complete. Track IDs are relinked
        $history = $this->user->getRecentTracks()->items;

        // Playlist MAY be relinked to playable tracks
        $relinked = [];
        foreach ($playlist->tracks->items as $plItem) {
            $key = "party.{$this->id}.relinked.{$plItem->track->id}";
            $relinked[$plItem->track->id] = Cache::remember($key, 86400, function() use ($plItem) {

                Log::debug("{$this}: Spotify API -> getTrack({$plItem->track->id})");
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
            $plItem->played_at = now()->subMilliseconds($plItem->duration_ms);
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
                Log::debug("{$this} Found playlist item in history that isn't first item - removing prior");
                $toRemove = array_merge($toRemove, array_slice($previous, 0, -1));
            }
        }

        if ($toRemove) {
            $idsToRemove = [
                'tracks' => array_map(function ($entry) {
                    return ['uri' => $entry->id];
                }, $toRemove),
            ];
            Log::debug("{$this}: Spotify API -> deletePlaylistTracks({$this->playlist_id}, [])");
            $this->user->getSpotifyApi()->deletePlaylistTracks($this->playlist_id, $idsToRemove);

            foreach ($toRemove as $track) {
                Log::info("{$this} Removing [{$track->id}] {$track->name}");
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

    protected function syncPlaylist(object $playlist)
    {
        Log::debug("{$this}: Updating playlist");
        $toAdd = self::PLAYLIST_LENGTH - $playlist->tracks->total;
        if ($toAdd < 1) {
            Log::debug("{$this}: No tracks to add");
            return;
        }

        $songsToAdd = $this->upcoming()
            ->whereNull('queued_at')
            ->orderBy('score', 'DESC')
            ->orderBy('created_at', 'ASC')
            ->orderBy('id', 'ASC')
            ->limit($toAdd)
            ->get();

        if ($songsToAdd->isEmpty()) {
            Log::debug("{$this}: Found no songs in the upcoming songs list to backfill");
            return;
        }

        $trackIds = [];
        foreach ($songsToAdd as $song) {
            $trackIds[] = $song->song->spotify_id;
        }
        Log::debug("{$this}: Spotify API -> addPlaylistTracks({$this->playlist_id}, [])");
        $this->user->getSpotifyApi()->addPlaylistTracks($this->playlist_id, $trackIds);
        foreach ($songsToAdd as $song) {
            if ($this->queue) {
                Log::info("{$this}: Adding {$song->song} to queue");
                Log::debug("{$this}: Spotify API -> queue({$song->song->spotify_id}, [])");
                $this->user->getSpotifyApi()->queue($song->song->spotify_id);
            }
            Log::info("{$this}: Added {$song->song} to party playlist");
            $song->queued_at = Carbon::now();
            $song->save();
        }
    }

    public function updateDeviceName(): void
    {
        if (!$this->device_id) {
            return;
        }

        if (!$this->isDirty('device_id')) {
            return;
        }

        $devices = $this->user->getDevices();
        foreach ($devices as $device) {
            if ($device->id == $this->device_id) {
                $this->device_name = $device->name;
                return;
            }
        }

        $this->device_name = 'Unknown';
    }

    public function updatePlaylist(): void
    {
        $api = $this->user->getSpotifyApi();
        if (!$this->playlist_id) {
            Log::debug("{$this}: Spotify API -> createPlaylist()");
            $playlist = $api->createPlaylist($api->me()->id, [
                'name' => "Music Party - {$this->code}",
                'description' => 'Automatically controlled Music Party playlist',
                'public' => true,
            ]);
            $this->playlist_id = $playlist->id;
            $this->save();
        } else {
            $playlist = $api->getPlaylist($this->playlist_id);
            if ($playlist->name !== "Music Party - {$this->code}") {
                Log::debug("{$this}: Spotify API -> updatePlaylist()");
                $api->updatePlaylist($this->playlist_id, [
                    'name' => "Music Party - {$this->code}",
                ]);
            }
        }
    }

    public function getMember(User $user): PartyMember
    {
        $member = $this->members()->whereUserId($user->id)->first();
        if ($member) {
            return $member;
        }
        $member = new PartyMember();
        $member->party()->associate($this);
        $member->user()->associate($user);

        $roleCode = 'user';
        if ($user->id === $this->user_id) {
            $roleCode = 'owner';
        }
        $role = PartyMemberRole::whereCode($roleCode)->first();
        $member->role()->associate($role);
        $member->save();
        return $member;
    }

    public function getNextUpdateDelay(): ?int
    {
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

    protected function forcePlayback(?object $current): ?object
    {
        if (!$this->force || !$this->device_id) {
            return $current;
        }

        if ($current && property_exists($current, 'item') && $current->item) {
            if ($current->is_playing) {
                return $current;
            } elseif ($this->song && $current->item->id == $this->song->spotify_id && $current->device->id == $this->device_id) {
                // Our current song, on our device, and it's paused. Let's not play anything - they can control it using the webpage
                return $current;
            }
        }

        $devices = $this->user->getDevices();
        foreach ($devices as $device) {
            if ($device->id == $this->device_id) {
                Log::info("{$this}: Forcing playback on {$device->name}");
                Log::debug("{$this}: Spotify API -> play()");
                $this->user->getSpotifyApi()->play($this->device_id, [
                    'context_uri' => "spotify:playlist:{$this->playlist_id}",
                ]);
                return $this->user->getSpotifyStatus();
            }
        }
        return $current;
    }

    protected function updateCurrentSong()
    {
        Log::debug("{$this}: Updating current song");
        $current = $this->user->getSpotifyStatus();
        $current = $this->forcePlayback($current);

        if ($this->song_id && (!$current || !property_exists($current, 'item') || !$current->item)) {
            Log::info("{$this}: Not playing anything");
            $this->song_id = null;
            $this->song_started_at = null;
            $this->save();
            return true;
        } elseif ($current) {
            $song = Song::fromSpotify($current->item);
            $shouldSave = false;
            if ($current->device && $current->device->id !== $this->recent_device_id) {
                $this->recent_device_id = $current->device->id;
                $shouldSave = true;
            }
            if ($song->id != $this->song_id) {
                Log::info("{$this}: Updating current song to {$song}");
                $this->song()->associate($song);
                $this->song_started_at = Carbon::now()->subMillis($current->progress_ms);
                $shouldSave = true;
            }
            if ($shouldSave) {
                $this->save();
                return true;
            }
        }
        return false;
    }

    protected function backfillUpcomingSongs()
    {
        Log::debug("{$this}: Backfilling upcoming songs");
        $toAdd = self::MINIMUM_UPCOMING - $this->upcoming()->whereNull('queued_at')->count();
        if ($toAdd <= 0) {
            return;
        }

        $tracks = Cache::remember("party.{$this->id}.backupplaylist", 600, function() {
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
            Log::info("{$this}: Adding {$song} from backup playlist");
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

            Log::debug("{$this}: Spotify API -> getPlaylistTracks({$this->backup_playlist_id}, {$offset})");
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

    public function canBeManagedBy(User $user): bool
    {
        if ($user->id === $this->owner_id) {
            return true;
        }
        return $this->members()->whereUserId($user->id)->whereHas('role', function ($query) {
                return $query->whereIn('code', ['owner']);
            })->count() > 0;
    }
}
