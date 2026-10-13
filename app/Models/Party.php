<?php

namespace App\Models;

use App\Events\Party\UpdatedEvent;
use App\Exceptions\VoteException;
use App\Models\Traits\ToString;
use App\Support\SpotifyPayload;
use Carbon\CarbonImmutable;
use Database\Factories\PartyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use NumPHP\LinAlg\LinAlg;
use Random\Randomizer;
use stdClass;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $user_id
 * @property int|null $song_id
 * @property Carbon|null $song_started_at
 * @property bool $active
 * @property bool $poll
 * @property bool $show_qrcode
 * @property int|null $downvotes_per_hour
 * @property bool $weighted
 * @property string|null $history_playlist_id
 * @property int|null $max_requests
 * @property bool $trustscore
 * @property int|null $trusted_user_id
 * @property bool $force
 * @property bool $explicit
 * @property bool $allow_requests
 * @property bool $downvotes
 * @property int|null $min_song_length
 * @property int|null $max_song_length
 * @property int|null $no_repeat_interval
 * @property string|null $device_id
 * @property string|null $recent_device_id
 * @property string|null $device_name
 * @property string|null $backup_playlist_id
 * @property string|null $backup_playlist_name
 * @property Carbon|null $last_updated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PlayedSong> $history
 * @property-read int|null $history_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PartyMember> $members
 * @property-read int|null $members_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PartyModeration> $moderations
 * @property-read int|null $moderations_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PartyModSetting> $modsettings
 * @property-read int|null $modsettings_count
 * @property-read Song|null $song
 * @property-read User|null $trustedUser
 * @property-read \Illuminate\Database\Eloquent\Collection<int, UpcomingSong> $upcoming
 * @property-read int|null $upcoming_count
 * @property-read User $user
 *
 * @method static \Database\Factories\PartyFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereAllowRequests($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereBackupPlaylistId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereBackupPlaylistName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereDeviceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereDeviceName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereDownvotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereDownvotesPerHour($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereExplicit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereForce($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereHistoryPlaylistId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereLastUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereMaxRequests($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereMaxSongLength($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereMinSongLength($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereNoRepeatInterval($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party wherePoll($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereRecentDeviceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereShowQrcode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereSongId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereSongStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereTrustedUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereTrustscore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereWeighted($value)
 *
 * @mixin \Eloquent
 */
class Party extends Model
{
    /** @use HasFactory<PartyFactory> */
    use HasFactory;

    use ToString;

    const MINIMUM_UPCOMING = 5;

    const QUEUE_LENGTH = 1;

    protected $attributes = [
        'allow_requests' => true,
        'explicit' => true,
        'downvotes' => true,
        'poll' => false,
        'active' => true,
        'show_qrcode' => false,
    ];

    protected $casts = [
        'active' => 'boolean',
        'poll' => 'boolean',
        'show_qrcode' => 'boolean',
        'weighted' => 'boolean',
        'trustscore' => 'boolean',
        'force' => 'boolean',
        'explicit' => 'boolean',
        'allow_requests' => 'boolean',
        'downvotes' => 'boolean',
        'last_updated_at' => 'datetime',
        'song_started_at' => 'datetime',
    ];

    /** @var Collection<string, PartyModSetting>|null */
    protected ?Collection $_modSettings = null;

    public function toStringName(): string
    {
        return $this->code;
    }

    public function getRouteKeyName()
    {
        return 'code';
    }

    /** @return HasMany<PartyMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(PartyMember::class);
    }

    /** @return HasMany<PartyModSetting, $this> */
    /** @return HasMany<PartyModSetting, $this> */
    public function modsettings(): HasMany
    {
        return $this->hasMany(PartyModSetting::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function trustedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trusted_user_id');
    }

    /** @return HasMany<UpcomingSong, $this> */
    public function upcoming(): HasMany
    {
        return $this->hasMany(UpcomingSong::class);
    }

    /** @return HasMany<PlayedSong, $this> */
    public function history(): HasMany
    {
        return $this->hasMany(PlayedSong::class);
    }

    /** @return BelongsTo<Song, $this> */
    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }

    /** @return HasMany<PartyModeration, $this> */
    public function moderations(): HasMany
    {
        return $this->hasMany(PartyModeration::class);
    }

    public function resolveChildRouteBinding($childType, $value, $field)
    {
        switch ($childType) {
            case 'upcomingsong':
            case 'song':
                return $this->upcoming()->whereId($value)->with(['song', 'user'])->first();

            case 'playedsong':
                return $this->history()->whereId($value)->with(['song', 'upcoming', 'upcoming.user'])->first();

            case 'user':
                return $this->members()->whereId($value)->with(['user', 'role'])->first();

            default:
                return parent::resolveChildRouteBinding($childType, $value, $field);
        }
    }

    public function current(): ?PlayedSong
    {
        return $this->history()->orderBy('played_at', 'desc')->first();
    }

    public function next(): ?UpcomingSong
    {
        return $this->upcoming()
            ->whereNotNull('queued_at')
            ->orderBy('queued_at', 'desc')
            ->first();
    }

    public function updateState(): Party
    {
        Log::info("{$this}: Updating state");
        if ($this->poll) {
            $cutoff = Carbon::now()->subSeconds(5);
            if ($this->last_updated_at > $cutoff) {
                Log::debug("{$this}: Already updated state recently");

                return $this;
            }
        }

        if (! $this->active) {
            Log::debug("{$this}: Party is not active");
            // Still update current song, in case they're using it manually and still want it reflected on screen
            $this->updateCurrentSong();

            return $this;
        }

        $this->updateCurrentSong();
        $this->addTracksToQueue();
        $this->backfillUpcomingSongs();
        $this->last_updated_at = Carbon::now();
        $this->save();
        Log::info("{$this}: Finished updating state");

        return $this;
    }

    protected function loadModSettings(): void
    {
        if ($this->_modSettings !== null) {
            return;
        }

        /** @var array<string, PartyModSetting> $settings */
        $settings = [];
        foreach ($this->modsettings()->with(['setting', 'setting.mod'])->get() as $setting) {
            $settings["{$setting->setting->mod->code}:{$setting->setting->code}"] = $setting;
        }
        foreach (ModSetting::orderBy('mod_id', 'asc')->orderBy('order', 'asc')->get() as $modSetting) {
            $key = "{$modSetting->mod->code}:{$modSetting->code}";
            if (! isset($settings[$key])) {
                $setting = new PartyModSetting;
                $setting->setting()->associate($modSetting);
                $setting->party()->associate($this);
                $setting->value = $modSetting->default;
                $settings[$key] = $setting;
            }
        }
        $this->_modSettings = new Collection($settings);
    }

    public function getModSettingValue(mixed $mod, string $code, mixed $default = null): mixed
    {
        $setting = $this->getModSetting($mod, $code);
        if ($setting !== null) {
            return $setting->value;
        }

        return $default;
    }

    public function getModSetting(mixed $mod, string $code): ?PartyModSetting
    {
        $this->loadModSettings();
        if ($this->_modSettings === null) {
            return null;
        }
        if ($mod instanceof Mod) {
            $mod = $mod->code;
        }
        $key = "{$mod}:{$code}";

        return $this->_modSettings->get($key);
    }

    public function setModSetting(mixed $mod, string $code, mixed $value): self
    {
        $setting = $this->getModSetting($mod, $code);
        if ($setting !== null) {
            $setting->value = $value;
            $setting->save();
            $this->load('modsettings');
        }

        return $this;
    }

    public function deleteModSetting(mixed $mod, string $code): self
    {
        $setting = $this->getModSetting($mod, $code);
        if ($setting === null) {
            return $this;
        }
        $setting->delete();
        $this->load('modsettings');

        return $this;
    }

    public function getState(): object
    {
        $next = $this->next();
        $current = $this->history()->orderBy('played_at', 'desc')->first();

        return (object) [
            'code' => $this->code,
            'name' => $this->name,
            'backup_playlist_id' => $this->backup_playlist_id,
            'status' => $this->getStatus(),
            'now' => $this->song?->toApi(),
            'current' => $current?->toApi(),
            'next' => $next?->toApi(),
            'show_qrcode' => $this->show_qrcode,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    protected function getStatus(): object
    {
        /** @var stdClass|null $status */
        $status = $this->user->status;

        if (! $status) {
            return (object) [
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

        $data = (object) [
            'device' => (object) [
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
            'updatedAt' => $this->user->status_updated_at?->toIso8601String(),
        ];

        if ($this->song && $status->item && $status->item->id == $this->song->spotify_id) {
            $data->active = true;
            $data->progress = $status->progress_ms;
            $data->length = $this->song->length;
        }

        return $data;
    }

    public function play(?string $playbackDevice): void
    {
        $playbackDevice ??= $this->recent_device_id;
        $playbackDevice ??= '';
        Log::debug("{$this}: Spotify API -> play()");
        $trackUri = '';
        if ($this->song) {
            $trackUri = "spotify:track:{$this->song->spotify_id}";
        }
        $current = $this->user->getSpotifyStatus();
        if ($current !== null && property_exists($current, 'item') && $current->item->id !== null && $current->item->type === 'track') {
            $trackUri = "spotify:track:{$current->item->id}";
        }
        if ($trackUri === '') {
            $song = $this->history()->orderBy('played_at', 'desc')->first();
            if ($song) {
                $trackUri = "spotify:track:{$song->song->spotify_id}";
            }
        }
        if ($trackUri === '') {
            // We are stuck, let's add something to the queue and use it
            $this->addTracksToQueue();
            $song = $this->upcoming()->orderBy('queued_at', 'desc')->first();
            if ($song) {
                $trackUri = "spotify:track:{$song->song->spotify_id}";
            }
        }
        $this->user->requireSpotifyApi()->play($playbackDevice, [
            'uris' => [$trackUri],
        ]);
    }

    public function pause(): void
    {
        Log::debug("{$this}: Spotify API -> pause()");
        $this->user->requireSpotifyApi()->pause($this->recent_device_id ?? '');
    }

    public function nextTrack(): void
    {
        Log::debug("{$this}: Spotify API -> next()");
        $this->user->requireSpotifyApi()->next($this->recent_device_id ?? '');
    }

    public function previousTrack(): void
    {
        Log::debug("{$this}: Spotify API -> seek()");
        $this->user->requireSpotifyApi()->seek([
            'position_ms' => 0,
            'device_id' => $this->recent_device_id,
        ]);
    }

    protected function getRelinkedTrackId(string $playedId): ?string
    {
        $key = "party.{$this->id}.relinkedtrackid.{$playedId}";

        return Cache::remember($key, 86400, function () use ($playedId) {
            Log::debug("{$this}: Spotify API -> getTrack({$playedId})");
            $data = SpotifyPayload::object($this->user->requireSpotifyApi()->getTrack($playedId, [
                'market',
            ]));

            return $data->id;
        });
    }

    protected function addTracksToQueue(): void
    {
        Log::debug("{$this}: Checking if we need to add tracks to queue");
        $this->user->getSpotifyStatus();
        $queue = SpotifyPayload::object($this->user->requireSpotifyApi()->getMyQueue());
        $queueItems = array_map(SpotifyPayload::object(...), (array) $queue->queue);
        $count = count($queueItems);
        $ids = collect($queueItems)->pluck('id')->unique();
        /* So let's talk about the Spotify queue. The Queue endpoint will return up to a maximum of 20 items, and a
         * minimum of 10. If the queue doesn't actually have anything, it will fill it up with multiple copies of
         * whatever is in the current context. This is utterly stupid.
         *
         * What this does mean, is that we can count the number of unique items in the queue. If we only have 1
         * unique item, then our queue is empty!
         *
         * So we take unique count, subtract 1 and then use that to determine how may tracks to add to the queue.
         */

        Log::debug("{$this}: Queue contains {$count} items, unique: {$ids->count()}");
        $toAdd = self::QUEUE_LENGTH - ($ids->count() - 1);
        if ($toAdd < 1) {
            Log::debug("{$this}: No tracks to add");

            return;
        }

        Log::debug("{$this}: Adding {$toAdd} tracks to the queue");

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
            $randomizer = app(Randomizer::class);
            $songsToAdd = new Collection;
            $allSongs = $this->upcoming()
                ->whereNull('queued_at')
                ->where(function ($query) {
                    $query->whereNull('not_before');
                    $query->orWhere('not_before', '<=', 'NOW()');
                })
                ->where('score', '>', 0)->get();
            $allSongs = collect($randomizer->shuffleArray($allSongs->all()));

            for ($i = 0; $i < $toAdd; $i++) {
                $sum = $allSongs->sum('score');
                $random = $randomizer->getInt(0, (int) $sum);
                foreach ($allSongs as $index => $song) {
                    $random -= $song->score;
                    if ($random <= 0) {
                        $allSongs->forget($index);
                        $songsToAdd->push($song);
                        break;
                    }
                }
            }
            $remaining = $toAdd - $songsToAdd->count();

            if ($remaining > 0) {
                // Didn't add enough songs, try and find *any* using old method
                $ids = $songsToAdd->pluck('id');
                $toAdd = $this->upcoming()
                    ->whereNull('queued_at')
                    ->whereNotIn('id', $ids)
                    ->where(function ($query) {
                        $query->whereNull('not_before');
                        $query->orWhere('not_before', '<=', 'NOW()');
                    })
                    ->orderBy('score', 'desc')
                    ->orderBy('created_at', 'asc')
                    ->orderBy('id', 'asc')
                    ->limit($remaining)
                    ->get();
                foreach ($toAdd as $song) {
                    $songsToAdd->push($song);
                }
            }
        } else {
            $songsToAdd = $this->upcoming()
                ->whereNull('queued_at')
                ->orderBy('score', 'desc')
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->limit($toAdd)
                ->get();
        }

        if ($songsToAdd->isEmpty()) {
            Log::debug("{$this}: Found no songs in the upcoming songs list to backfill");

            return;
        }
        foreach ($songsToAdd as $song) {
            Log::info("{$this}: Adding {$song->song} to queue");
            Log::debug("{$this}: Spotify API -> queue({$song->song->spotify_id}, [])");
            $this->user->requireSpotifyApi()->queue($song->song->spotify_id);
            $song->queued_at = Carbon::now();
            $song->save();
            if ($this->history_playlist_id !== null) {
                Log::info("{$this}: Adding {$song->song} to history playlist");
                Log::debug("{$this}: Spotify API -> addPlaylistTracks({$this->history_playlist_id}, [{$song->song->spotify_id}])");
                $this->user->requireSpotifyApi()->addPlaylistTracks($this->history_playlist_id, [$song->song->spotify_id]);
            }
        }
    }

    public function updateDeviceName(): void
    {
        if (! $this->device_id) {
            return;
        }

        if (! $this->isDirty('device_id')) {
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

    public function getMember(User $user): PartyMember
    {
        $member = $this->members()->whereUserId($user->id)->first();
        if ($member) {
            return $member;
        }
        $member = new PartyMember;
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
        if (! $this->poll) {
            return null;
        }

        if (! $this->song) {
            return 60;
        }

        /** @var stdClass|null $status */
        $status = $this->user->status;
        if (! $status || ! $status->is_playing) {
            return 60;
        }

        $remaining = ($this->song->length - $status->progress_ms) / 1000;
        if ($remaining > 60) {
            return 60;
        }

        return max(5, (int) floor($remaining / 2));
    }

    protected function forcePlayback(?stdClass $current): ?stdClass
    {
        if (! $this->force || ! $this->device_id) {
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
        $trackUri = '';
        if ($this->song) {
            $trackUri = "spotify:track:{$this->song->spotify_id}";
        }
        if (is_object($current) && property_exists($current, 'item') && $current->item->id !== null && $current->item->type === 'track') {
            $trackUri = "spotify:track:{$current->item->id}";
        }
        foreach ($devices as $device) {
            if ($device->id == $this->device_id) {
                Log::info("{$this}: Forcing playback on {$device->name}");
                Log::debug("{$this}: Spotify API -> play()");
                $this->user->requireSpotifyApi()->play($this->device_id, [
                    'uris' => [$trackUri],
                ]);

                return $this->user->getSpotifyStatus();
            }
        }

        return $current;
    }

    protected function updateCurrentSong(): bool
    {
        Log::debug("{$this}: Updating current song");
        $current = $this->user->getSpotifyStatus();
        $current = $this->forcePlayback($current);

        if ($this->song_id && (! $current || ! property_exists($current, 'item') || ! $current->item)) {
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

                $playedSong = new PlayedSong;
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

    protected function backfillUpcomingSongs(): void
    {
        Log::debug("{$this}: Backfilling upcoming songs");
        $toAdd = self::MINIMUM_UPCOMING - $this->upcoming()->whereNull('queued_at')->count();
        if ($toAdd <= 0) {
            return;
        }

        $tracks = $this->getBackupPlaylistTracks();

        $existingIds = $this->upcoming()
            ->where(function ($query) {
                $query->whereNull('queued_at')
                    ->orWhere('queued_at', '>', Carbon::now()->subMinutes(30));
            })
            ->with('song')
            ->get()
            ->pluck('song.spotify_id')
            ->toArray();

        $tracks = array_filter($tracks, function ($track) use ($existingIds) {
            if ($track->track === null) {
                return false;
            }

            return ! in_array($track->track->id, $existingIds);
        });

        $toAdd = min($toAdd, count($tracks));
        $tracks = app(Randomizer::class)->shuffleArray(array_values($tracks));

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

    /** @return array<int, stdClass> */
    public function getBackupPlaylistTracks(bool $force = false): array
    {
        $cacheKey = "party.{$this->id}.backupplaylist";
        $tracks = Cache::get($cacheKey);
        if ($tracks !== null && ! $force) {
            return $tracks;
        }

        if ($this->backup_playlist_id === null) {
            return [];
        }

        $tracks = [];
        $offset = 0;
        do {
            Log::debug("{$this}: Spotify API -> getPlaylistTracks({$this->backup_playlist_id}, {$offset})");
            $response = SpotifyPayload::object($this->user->requireSpotifyApi()->getPlaylistTracks($this->backup_playlist_id, [
                'limit' => 50,
                'offset' => $offset,
                'market' => $this->user->market,
            ]));
            foreach ($response->items as $track) {
                if ($track->track === null) {
                    continue;
                }
                if (property_exists($track->track, 'is_playable') && ! $track->track->is_playable) {
                    Log::debug("{$this}: Ignoring [{$track->track->id}] {$track->track->name} because it is not playable");

                    continue;
                }
                if ($track->track->is_local) {
                    Log::debug("{$this}: Ignoring [{$track->track->id}] {$track->track->name} because it is local");

                    continue;
                }
                $tracks[] = $track;
            }
            $offset += 50;
        } while ($response->next !== null);

        Cache::put($cacheKey, $tracks, 3600);

        return $tracks;
    }

    public function canBeManagedBy(?User $user): bool
    {
        if ($user === null) {
            return false;
        }
        if ($user->id === $this->user_id) {
            return true;
        }

        return $this->members()->whereUserId($user->id)->whereHas('role', fn ($query) => $query->whereIn('code', ['owner']))->count() > 0;
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
            Log::debug('No trusted user, unable to calculate trust score');

            return;
        }

        $voteMap = [];
        $userIds = $this->members()->pluck('user_id');
        $userIds->push(0);
        $fill = [];
        foreach ($userIds as $id) {
            $fill[$id] = 0;
        }
        foreach ($userIds as $id) {
            $voteMap[$id] = $fill;
        }

        // Votes
        $query = Vote::whereHas('upcomingSong', function ($query) {
            $query->wherePartyId($this->id);
        })->where('value', '>', 0);
        if ($after !== null) {
            $query = $query->where('created_at', '>', $after->format('Y-m-d H:i:s'));
        }
        $votes = $query->with(['user', 'upcomingSong', 'upcomingSong.user'])->get();
        foreach ($votes as $vote) {
            $songUserId = $vote->upcomingSong->user->id ?? 0;
            $voteMap[$songUserId][$vote->user->id] += $vote->value;
        }

        // Ratings
        $query = SongRating::whereHas('song', function ($query) {
            $query->wherePartyId($this->id);
        })->where('value', '>', 0);
        if ($after !== null) {
            $query = $query->where('created_at', '>', $after->format('Y-m-d H:i:s'));
        }
        $ratings = $query->with(['user', 'song', 'song.upcoming', 'song.upcoming.user'])->get();
        foreach ($ratings as $rating) {
            if ($rating->song->upcoming) {
                $songUserId = $rating->song->upcoming->user->id ?? 0;
                $voteMap[$songUserId][$rating->user->id] += $rating->value;
            }
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

        DB::transaction(function () use ($userMap, $scores) {
            $this->members()->update([
                'trustscore' => 0,
            ]);
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
                if (! isset($membersIndexed[$userId])) {
                    continue;
                }
                $membersIndexed[$userId]->trustscore = $score;
                $membersIndexed[$userId]->save();
            }
        });
        Log::debug('Finished calculating trust scores');
    }
}
