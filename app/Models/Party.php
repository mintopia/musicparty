<?php

namespace App\Models;

use App\Casts\MillisecondTimestamp;
use App\Events\Party\UpdatedEvent;
use App\Exceptions\VoteException;
use App\Models\Traits\ToString;
use App\Services\Music\Data\PlaybackSource;
use App\Services\PlaybackStatus;
use Database\Factories\PartyFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property PlaybackSource|null $playback_source
 * @property Carbon|null $playback_received_at
 * @property Carbon|null $playback_sampled_at
 * @property string $code
 * @property string $name
 * @property string $country
 * @property string|null $timezone
 * @property string $provider_id
 * @property int $user_id
 * @property int|null $song_id
 * @property Carbon|null $song_started_at
 * @property bool $active
 * @property bool $poll
 * @property bool $show_qrcode
 * @property int|null $downvotes_per_hour
 * @property bool $weighted
 * @property string|null $history_playlist_id
 * @property string|null $context_playlist_id
 * @property int|null $max_requests
 * @property bool $flag_requests
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
 * @property-read Collection<int, PlayedSong> $history
 * @property-read int|null $history_count
 * @property-read Collection<int, PartyMember> $members
 * @property-read int|null $members_count
 * @property-read Collection<int, PartyModeration> $moderations
 * @property-read int|null $moderations_count
 * @property-read Collection<int, Mod> $mods
 * @property-read int|null $mods_count
 * @property-read Collection<int, PartyModSetting> $modsettings
 * @property-read int|null $modsettings_count
 * @property-read Song|null $song
 * @property-read Collection<int, UpcomingSong> $upcoming
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

    const PUSHED_PLAYBACK_FRESH_SECONDS = 30;

    protected $attributes = [
        'allow_requests' => true,
        'explicit' => true,
        'downvotes' => true,
        'poll' => false,
        'active' => true,
        'show_qrcode' => true,
        'flag_requests' => false,
    ];

    protected $casts = [
        'active' => 'boolean',
        'poll' => 'boolean',
        'show_qrcode' => 'boolean',
        'weighted' => 'boolean',
        'force' => 'boolean',
        'explicit' => 'boolean',
        'allow_requests' => 'boolean',
        'downvotes' => 'boolean',
        'flag_requests' => 'boolean',
        'last_updated_at' => 'datetime',
        'song_started_at' => 'datetime',
        'playback_source' => PlaybackSource::class,
        'playback_received_at' => MillisecondTimestamp::class,
        'playback_sampled_at' => MillisecondTimestamp::class,
    ];

    /** @var array<int, string> */
    public const array RENDERED_ATTRIBUTES = [
        'code', 'name', 'backup_playlist_id', 'show_qrcode', 'song_id', 'song_started_at',
    ];

    protected bool $coalescingBroadcasts = false;

    public function saveWithGeneratedCode(int $attempts = 5): void
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                $this->save();

                return;
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= $attempts) {
                    throw $e;
                }
                $this->setAttribute('code', null);
            }
        }
    }

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

    /** @return BelongsToMany<Mod, $this> */
    public function mods(): BelongsToMany
    {
        return $this->belongsToMany(Mod::class, 'party_mods')->withTimestamps();
    }

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

    /** @return HasMany<UpcomingSong, $this> */
    public function upcoming(): HasMany
    {
        return $this->hasMany(UpcomingSong::class);
    }

    /** @return HasMany<AiReview, $this> */
    public function aiReviews(): HasMany
    {
        return $this->hasMany(AiReview::class);
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
            case 'upcomingSong':
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

    /**
     * @return array{playedSongId: int, value: int}|null
     */
    public function ratingBy(User $user): ?array
    {
        $current = $this->current();
        if ($current === null) {
            return null;
        }

        return [
            'playedSongId' => $current->id,
            'value' => (int) ($current->ratings()->whereUserId($user->id)->value('value') ?? 0),
        ];
    }

    public function next(): ?UpcomingSong
    {
        return $this->upcoming()
            ->whereNotNull('queued_at')
            ->orderBy('queued_at', 'desc')
            ->first();
    }

    public function getState(): object
    {
        $next = $this->next();
        $current = $this->history()->orderBy('played_at', 'desc')->first();

        return (object) [
            'code' => $this->code,
            'name' => $this->name,
            'backup_playlist_id' => $this->backup_playlist_id,
            'status' => app(PlaybackStatus::class)->forParty($this),
            'now' => $this->song?->toApi(),
            'current' => $current?->toApi(),
            'next' => $next?->toApi(),
            'show_qrcode' => $this->show_qrcode,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    public function getMember(User $user): PartyMember
    {
        $member = $this->members()->whereUserId($user->id)->first();
        if ($member) {
            return $member;
        }

        $roleCode = $user->id === $this->user_id ? 'owner' : 'user';
        $roleId = PartyMemberRole::whereCode($roleCode)->value('id');

        try {
            return $this->members()->firstOrCreate(['user_id' => $user->id], ['role_id' => $roleId]);
        } catch (UniqueConstraintViolationException) {
            return $this->members()->whereUserId($user->id)->firstOrFail();
        }
    }

    public function canParticipate(User $user): bool
    {
        if ($user->id === $this->user_id) {
            return true;
        }
        $member = $this->members()->whereUserId($user->id)->with('role')->first();

        return $member !== null && $member->role->code !== 'banned';
    }

    public function hasFreshPushedPlayback(): bool
    {
        return $this->playback_source?->isPushed() === true
            && $this->playback_received_at !== null
            && $this->playback_received_at->greaterThan(now()->subSeconds(self::PUSHED_PLAYBACK_FRESH_SECONDS));
    }

    public function roleCodeFor(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }
        if ($user->id === $this->user_id) {
            return 'owner';
        }

        return $this->members()->whereUserId($user->id)->first()?->role?->code;
    }

    public function roleCodeForMember(PartyMember $member): ?string
    {
        if ($member->user_id === $this->user_id) {
            return 'owner';
        }

        return $member->role->code;
    }

    public function canBeManagedBy(?User $user): bool
    {
        return $this->roleCodeFor($user) === 'owner';
    }

    public function canBeModeratedBy(?User $user): bool
    {
        return in_array($this->roleCodeFor($user), ['owner', 'moderator'], true);
    }

    public function pushUpdate(): void
    {
        UpdatedEvent::dispatch($this);
    }

    public function suppressRenderedBroadcasts(bool $suppress): void
    {
        $this->coalescingBroadcasts = $suppress;
    }

    public function broadcastRenderedChange(): void
    {
        if (! $this->coalescingBroadcasts) {
            UpdatedEvent::dispatch($this);
        }
    }

    public function checkDownvotesForUser(User $user): void
    {
        if ($this->downvotes_per_hour === null) {
            return;
        }
        $downvotes = Vote::whereUserId($user->id)
            ->where('value', '<', 0)
            ->where('value_changed_at', '>=', now()->subHour())
            ->whereHas('upcomingSong', function ($query) {
                $query->wherePartyId($this->id);
            })->count();
        if ($downvotes >= $this->downvotes_per_hour) {
            throw new VoteException('You have downvoted too many songs');
        }
    }
}
