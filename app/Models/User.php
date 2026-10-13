<?php

namespace App\Models;

use App\Models\Traits\ToString;
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
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;

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

    public function getPartyMember(Party $party): ?PartyMember
    {
        foreach ($this->partyMembers as $member) {
            if ($member->party_id === $party->id) {
                return $member;
            }
        }

        return null;
    }
}
