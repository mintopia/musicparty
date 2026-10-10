<?php

namespace App\Domain\Identity\Models;

use App\Domain\Admin\Models\AdminHostSession;
use App\Domain\Admin\Models\Role;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Models\Party;
use App\Domain\Theming\ColourScheme;
use App\Support\Concerns\ToString;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @mixin IdeHelperUser
 */
#[UseFactory(UserFactory::class)]
class User extends Authenticatable
{
    /** @var array<int, string>|null */
    private ?array $roleCodes = null;

    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;
    use ToString;

    protected $casts = [
        'terms_agreed_at' => 'datetime',
        'first_login' => 'boolean',
        'suspended' => 'boolean',
        'last_login' => 'datetime',
        'colour_scheme' => ColourScheme::class,
    ];

    protected ?string $email = null;

    public function hasCompletedSignup(): bool
    {
        return ! $this->first_login && $this->terms_agreed_at !== null;
    }

    /**
     * @return HasMany<LinkedAccount>
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(LinkedAccount::class);
    }

    /**
     * @return HasMany<Party>
     */
    public function parties(): HasMany
    {
        return $this->hasMany(Party::class);
    }

    /**
     * @return BelongsToMany<Party, $this>
     */
    public function memberParties(): BelongsToMany
    {
        return $this->belongsToMany(Party::class, 'party_members')->withPivot('role')->withTimestamps();
    }

    /**
     * @return HasMany<PartyMember, $this>
     */
    public function partyMembers(): HasMany
    {
        return $this->hasMany(PartyMember::class);
    }

    public function hasRole(string|Role $role): bool
    {
        if ($role instanceof Role) {
            $role = $role->code;
        }

        return in_array($role, $this->roleCodes(), true);
    }

    /**
     * @return array<int, string>
     */
    private function roleCodes(): array
    {
        return $this->roleCodes ??= $this->roles()->pluck('code')->all();
    }

    public function forgetRoleCache(): void
    {
        $this->roleCodes = null;
    }

    public function refresh(): static
    {
        $this->roleCodes = null;

        return parent::refresh();
    }

    public function isActingAsHostIn(Party $party): bool
    {
        return $this->hasRole('admin')
            && AdminHostSession::query()->active()->where('user_id', $this->id)->where('party_id', $party->id)->exists();
    }

    public function getEmail(): ?string
    {
        if ($this->email !== null) {
            return $this->email;
        }
        $linked = $this->accounts->first(fn (LinkedAccount $account): bool => $account->email !== null);
        if ($linked) {
            $this->email = $linked->email;

            return $this->email;
        }

        return null;
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
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
