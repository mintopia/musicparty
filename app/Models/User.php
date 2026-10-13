<?php

namespace App\Models;

use App\Models\Traits\ToString;
use App\Services\Spotify\SpotifyGateway;
use App\Support\SpotifyPayload;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;
use stdClass;

/**
 * @property int $id
 * @property string $nickname
 * @property string $market
 * @property string|null $avatar
 * @property Carbon|null $terms_agreed_at
 * @property bool $first_login
 * @property Carbon|null $last_login
 * @property bool $suspended
 * @property object|null $status
 * @property Carbon|null $status_updated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, LinkedAccount> $accounts
 * @property-read int|null $accounts_count
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read Collection<int, Party> $parties
 * @property-read int|null $parties_count
 * @property-read Collection<int, PartyMember> $partyMembers
 * @property-read int|null $party_members_count
 * @property-read Collection<int, SongRating> $ratings
 * @property-read int|null $ratings_count
 * @property-read Collection<int, Role> $roles
 * @property-read int|null $roles_count
 * @property-read Collection<int, PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @property-read Collection<int, Vote> $votes
 * @property-read int|null $votes_count
 *
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAvatar($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFirstLogin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastLogin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereMarket($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereNickname($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereStatusUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereSuspended($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTermsAgreedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;
    use ToString;

    protected $casts = [
        'first_login' => 'boolean',
        'suspended' => 'boolean',
        'terms_agreed_at' => 'datetime',
        'last_login' => 'datetime',
        'status_updated_at' => 'datetime',
        'status' => 'object',
    ];

    protected ?string $email = null;

    /** @var array<int, stdClass>|null */
    protected ?array $playlists = null;

    /** @var array<int, stdClass>|null */
    protected ?array $devices = null;

    protected ?stdClass $recentTracks = null;

    /** @var array<string, stdClass> */
    protected array $playlistCache = [];

    /** @return HasMany<LinkedAccount, $this> */
    public function accounts(): HasMany
    {
        return $this->hasMany(LinkedAccount::class);
    }

    /** @return HasMany<Party, $this> */
    public function parties(): HasMany
    {
        return $this->hasMany(Party::class);
    }

    /** @return HasMany<PartyMember, $this> */
    public function partyMembers(): HasMany
    {
        return $this->hasMany(PartyMember::class);
    }

    /** @return HasMany<Vote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    /** @return HasMany<SongRating, $this> */
    public function ratings(): HasMany
    {
        return $this->hasMany(SongRating::class);
    }

    public function hasRole(string|Role $role): bool
    {
        if ($role instanceof Role) {
            $role = $role->code;
        }

        return (bool) $this->roles()->whereCode($role)->count();
    }

    public function getEmail(): ?string
    {
        if ($this->email !== null) {
            return $this->email;
        }
        $linked = $this->accounts()->whereNotNull('email')->first();
        if ($linked) {
            $this->email = $linked->email;

            return $this->email;
        }

        return null;
    }

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function avatarUrl(): string
    {
        foreach ($this->accounts as $acc) {
            if ($acc->avatar_url) {
                return $acc->avatar_url;
            }
        }
        if ($email = $this->getEmail()) {
            $toHash = $email;
        } else {
            $toHash = $this->nickname;
        }
        $hash = hash('sha256', $toHash);

        return "https://gravatar.com/avatar/{$hash}?d=retro";
    }

    protected function toStringName(): string
    {
        return $this->nickname;
    }

    public function hasSpotifyLinks(): bool
    {
        $count = $this->accounts()->whereHas('provider', function ($query) {
            $query->whereIn('code', ['spotify', 'spotifysearch']);
        })->count();

        return $count === 2;
    }

    public function spotifyNeedsRelink(): bool
    {
        return $this->accounts()
            ->whereNotNull('needs_relink_at')
            ->whereHas('provider', fn ($query) => $query->whereCode('spotify'))
            ->exists();
    }

    /**
     * @return array<int, stdClass>
     */
    public function getDevices(): array
    {
        if ($this->devices !== null) {
            return $this->devices;
        }
        $api = app(SpotifyGateway::class)->client($this);
        if (! $api) {
            return [];
        }

        /** @var array<int, stdClass> $devices */
        $devices = Cache::remember("users.{$this->id}.devices", 300, function () use ($api): array {
            Log::debug("{$this}: Spotify API -> getMyDevices()");

            return (array) SpotifyPayload::object($api->getMyDevices())->devices;
        });
        $this->devices = $devices;

        return $devices;
    }

    /**
     * @return array<int, stdClass>
     */
    public function getPlaylists(): array
    {
        if ($this->playlists !== null) {
            return $this->playlists;
        }
        $api = app(SpotifyGateway::class)->client($this);
        if (! $api) {
            return [];
        }

        $this->playlists = [];
        $offset = 0;
        do {
            Log::debug("{$this}: Spotify API -> getMyPlaylists({$offset})");
            $result = SpotifyPayload::object($api->getMyPlaylists(['limit' => 50, 'offset' => $offset]));
            $this->playlists = array_merge($this->playlists, $result->items);
            $offset += 50;
        } while ($result->next);

        return $this->playlists;
    }

    public function getPartyMember(Party $party): ?PartyMember
    {
        foreach ($this->partyMembers as $member) {
            if ($member->party_id === $party->id) {
                return $member;
            }
        }

        return null;
    }

    public function getRecentTracks(bool $force = false): stdClass
    {
        if (! $force && $this->recentTracks !== null) {
            return $this->recentTracks;
        }

        Log::debug("{$this}: Spotify API -> getMyRecentTracks()");
        $this->recentTracks = SpotifyPayload::object(app(SpotifyGateway::class)->requireClient($this)->getMyRecentTracks([
            'limit' => 20,
            'market' => $this->market,
        ]));

        return $this->recentTracks;
    }

    public function getPlaylist(string $id, bool $force = false): stdClass
    {
        if (! $force && isset($this->playlistCache[$id])) {
            return $this->playlistCache[$id];
        }

        Log::debug("{$this}: Spotify API -> getPlaylist({$id})");
        $this->playlistCache[$id] = SpotifyPayload::object(app(SpotifyGateway::class)->requireClient($this)->getPlaylist($id, [
            'market' => $this->market,
        ]));

        return $this->playlistCache[$id];
    }
}
