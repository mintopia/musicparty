<?php

namespace App\Models;

use App\Events\Party\UpdatedEvent;
use App\Exceptions\VoteException;
use App\Models\Traits\ToString;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use NumPHP\LinAlg\LinAlg;

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
        'poll' => true,
        'active' => true,
        'show_qrcode' => false,
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

    public function trustedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trusted_user_id');
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

    public function current()
    {
        return $this->history()->orderBy('played_at', 'DESC')->first();
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
        if ($this->playlist_id === null) {
            $this->updatePlaylist();
        }
        $cutoff = Carbon::now()->subSeconds(5);
        if ($this->last_updated_at > $cutoff) {
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
        $current = $this->history()->orderBy('played_at', 'desc')->first();
        return (object)[
            'code' => $this->code,
            'name' => $this->name,
            'backup_playlist_id' => $this->backup_playlist_id,
            'status' => $this->getStatus(),
            'now' => $this->song?->toApi(),
            'current' => $current?->toApi(),
            'next' => $next?->toApi(),
            'show_qrcode' => $this->show_qrcode,
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

        if ($this->song && $status->item && $status->item->id == $this->song->spotify_id) {
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


    protected function getPlaylist(bool $force = false): object
    {
        return $this->user->getPlaylist($this->playlist_id, $force);
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

    protected function getRelinkedTrackId(string $playedId): ?string
    {
        $key = "party.{$this->id}.relinkedtrackid.{$playedId}";
        return Cache::remember($key, 86400, function() use ($playedId) {
            Log::debug("{$this}: Spotify API -> getTrack({$playedId})");
            $data = $this->user->getSpotifyApi()->getTrack($playedId, [
                'market'
            ]);
            return $data->id;
        });
    }

    protected function updateHistory(): object
    {
        Log::debug("{$this} Updating history");
        $playlist = $this->getPlaylist(true);

        $current = $this->current();
        $next = $this->next();

        $allowedIds = array_unique(array_filter([
            $current->song->spotify_id ?? null,
            $current->relinked_from ?? null,
            $next->song->spotify_id ?? null,
            $next->relinked_from ?? null,
        ]));

        $toRemove = [];
        foreach ($playlist->tracks->items as $playlistItem) {
            if (!in_array($playlistItem->track->id, $allowedIds)) {
                $toRemove[] = $playlistItem->track->id;
            }
        }

        if ($toRemove) {
            $idsToRemove = [
                'tracks' => array_map(function ($id) {
                    return ['uri' => $id];
                }, $toRemove),
            ];
            Log::debug("{$this}: Spotify API -> deletePlaylistTracks({$this->playlist_id}, [])");
            $this->user->getSpotifyApi()->deletePlaylistTracks($this->playlist_id, $idsToRemove);

            $playlist = $this->getPlaylist(true);
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

        /*
         * Notes by Cwis
         * Remove the limit - fetch everything
         * Get sum of score for everything
         * Get random number up to total sum of score
         * Iterate through songs
         * For each song, decrease random number
         * when it drops below zero, take current song
         *
         */

        if ($this->weighted) {
            $songsToAdd = new Collection();
            $allSongs = $this->upcoming()
                ->whereNull('queued_at')
                ->where('score', '>', 0)->inRandomOrder()->get();

            for ($i = 0; $i < $toAdd; $i++) {
                $sum = $allSongs->sum('score');
                $random = mt_rand(0, $sum);
                foreach ($allSongs as $index => $song) {
                    $random -= $song->score;
                    if ($random <= 0) {
                        $allSongs->forget($index);
                        $songsToAdd->push($song);
                        break;
                    }
                }
            }
            $remaining = $toAdd = $songsToAdd->count();

            if ($remaining > 0) {
                // Didn't add enough songs, try and find *any* using old method
                $ids = $songsToAdd->pluck('id');
                $toAdd = $this->upcoming()
                    ->whereNull('queued_at')
                    ->whereNotIn('id', $ids)
                    ->orderBy('score', 'DESC')
                    ->orderBy('created_at', 'ASC')
                    ->orderBy('id', 'ASC')
                    ->limit($remaining)
                    ->get();
                foreach ($toAdd as $song) {
                    $songsToAdd->push($song);
                }
            }
        } else {
            $songsToAdd = $this->upcoming()
                ->whereNull('queued_at')
                ->orderBy('score', 'DESC')
                ->orderBy('created_at', 'ASC')
                ->orderBy('id', 'ASC')
                ->limit($toAdd)
                ->get();
        }

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
        if (!$this->poll) {
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
            if ($current->device && $current->device->id !== $this->recent_device_id) {
                $this->recent_device_id = $current->device->id;
            }
            if ($song->id != $this->song_id) {
                Log::info("{$this}: Updating current song to {$song}");
                $this->song()->associate($song);
                $this->song_started_at = Carbon::now()->subMillis($current->progress_ms);

                $playedSong = new PlayedSong();
                $playedSong->song()->associate($song);
                $playedSong->party()->associate($this);
                $playedSong->played_at = $this->song_started_at;
                $relinkedId = $this->getRelinkedTrackId($song->spotify_id);
                if ($relinkedId) {
                    $playedSong->relinked_from = $relinkedId;
                }
                $playedSong->findRequestedSong();
                $playedSong->save();
            }
            if ($this->isDirty()) {
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

        $tracks = $this->getBackupPlaylistTracks();

        $existingIds = $this->upcoming()
            ->where(function($query) {
                $query->whereNull('queued_at')
                    ->orWhere('queued_at', '>', Carbon::now()->subMinutes(30));})
            ->with('song')
            ->get()
            ->pluck('song.spotify_id')
            ->toArray();

        $tracks = array_filter($tracks, function ($track) use ($existingIds) {
            if ($track->track === null) {
                return false;
            }
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

    public function getBackupPlaylistTracks(bool $force = false): array
    {
        $cacheKey = "party.{$this->id}.backupplaylist";
        $tracks = Cache::get($cacheKey);
        if ($tracks !== null) {
            return $tracks;
        }

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

        Cache::put($cacheKey, $tracks, 3600);
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

    public function pushUpdate(): void
    {
        UpdatedEvent::dispatch($this);
    }

    public function checkDownvotesForUser(User $user): void
    {
        if ($this->downvotes_per_hour === null) {
            return;
        }
        $downvotes = Vote::whereUserId($user->id)
            ->where('value', '<', 0)
            ->where('created_at', '>=', now()->subHour())
            ->whereHas('upcomingSong', function ($query) {
                $query->wherePartyId($this->id);
            })->count();
        if ($downvotes >= $this->downvotes_per_hour) {
            throw new VoteException('You have downvoted too many songs');
        }
    }

    public function calculateTrustScores(?CarbonImmutable $after = null): void
    {
        if ($this->trustedUser === null) {
            Log::debug("No trusted user, unable to calculate trust score");
            return;
        }

        $voteMap = [];
        $query = Vote::whereHas('upcomingSong', function ($query) {
            $query->wherePartyId($this->id);
        })->where('value', '>', 0);
        if ($after !== null) {
            $query = $query->where('created_at', '>', $after->format('Y-m-d H:i:s'));
        }

        $votes = $query->with(['user', 'upcomingSong', 'upcomingSong.user'])->get();

        $userIds = $votes->pluck('user_id');
        $userIds->push(0);
        $fill = [];
        foreach ($userIds as $id) {
            $fill[$id] = 0;
        }
        foreach ($userIds as $id) {
            $voteMap[$id] = $fill;
        }
        foreach ($votes as $vote) {
            $songUserId = $vote->upcomingSong->user->id ?? 0;
            $voteMap[$vote->user->id][$songUserId] += $vote->value;
        }
        $userMap = array_keys($voteMap);
        $voteMap = array_values($voteMap);
        foreach ($voteMap as $i => $row) {
            $voteMap[$i] = array_values($row);
            $voteMap[$i][$i] = -1;
            if ($userMap[$i] === $this->trustedUser->id) {
                $voteMap[$i][$i] = 1;
            }
        }

        $trustedUserMatrix = array_fill(0, count($voteMap), 0);
        $trustedUserIndex = array_search($this->trustedUser->id, $userMap);
        $trustedUserMatrix[$trustedUserIndex] = 1;

        $solved = LinAlg::solve($voteMap, $trustedUserMatrix);
        $scores = $solved->getData();

        $membersIndexed = [];
        $members = $this->members()->with('user')->get();
        foreach ($members as $member) {
            $membersIndexed[$member->user->id] = $member;
        }

        foreach ($scores as $index => $score) {
            $userId = $userMap[$index] ?? null;
            if ($userId === null) {
                continue;
            }
            if (!isset($membersIndexed[$userId])) {
                continue;
            }
            $membersIndexed[$userId]->trustscore = $score;
            $membersIndexed[$userId]->save();
        }
        Log::debug("Finished calculating trust scores");
    }
}
