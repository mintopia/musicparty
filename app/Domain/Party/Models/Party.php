<?php

namespace App\Domain\Party\Models;

use App\Domain\Admin\Models\AdminHostSession;
use App\Domain\Identity\Models\User;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Membership\PartyRole;
use App\Domain\Party\PartyState;
use App\Domain\Queue\SelectionMode;
use App\Support\Concerns\ToString;
use Database\Factories\PartyFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property PartyState $state
 * @property string $music_provider
 *
 * @mixin IdeHelperParty
 */
#[UseFactory(PartyFactory::class)]
class Party extends Model
{
    use HasApiTokens;
    use HasFactory;
    use ToString;

    protected $attributes = [
        'allow_requests' => true,
        'explicit' => true,
        'downvotes' => true,
        'state' => 'paused',
        'selection_mode' => 'deterministic',
        'tv_layout' => 'default',
    ];

    protected $casts = [
        'state' => PartyState::class,
        'selection_mode' => SelectionMode::class,
        'allow_requests' => 'boolean',
        'explicit' => 'boolean',
        'downvotes' => 'boolean',
        'hold_requests' => 'boolean',
        'downvotes_per_hour' => 'integer',
        'max_requests' => 'integer',
        'min_song_length' => 'integer',
        'max_song_length' => 'integer',
        'no_repeat_interval' => 'integer',
        'theme' => 'array',
    ];

    public function toStringName(): string
    {
        return $this->code;
    }

    public function getRouteKeyName()
    {
        return 'code';
    }

    public static function findByCode(string $code): ?self
    {
        return static::query()->where('code', Str::upper(trim($code)))->first();
    }

    public function resolveRouteBinding($value, $field = null)
    {
        if ($field === null || $field === 'code') {
            return self::findByCode((string) $value);
        }

        return parent::resolveRouteBinding($value, $field);
    }

    public function memberFor(User $user): ?PartyMember
    {
        /** @var PartyMember|null */
        return $this->members()->whereUserId($user->id)->first();
    }

    public function members(): HasMany
    {
        return $this->hasMany(PartyMember::class);
    }

    /**
     * @return HasMany<BlocklistEntry, $this>
     */
    public function blocklistEntries(): HasMany
    {
        return $this->hasMany(BlocklistEntry::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<AdminHostSession, $this>
     */
    public function adminHostSessions(): HasMany
    {
        return $this->hasMany(AdminHostSession::class);
    }

    public function canBeManagedBy(User $user): bool
    {
        if ($user->id === $this->user_id || $user->isActingAsHostIn($this)) {
            return true;
        }

        return $this->members()->whereUserId($user->id)->where('role', PartyRole::Host->value)->exists();
    }
}
