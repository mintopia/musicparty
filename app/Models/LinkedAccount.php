<?php

namespace App\Models;

use App\Models\Traits\ToString;
use Database\Factories\LinkedAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $social_provider_id
 * @property string|null $external_id
 * @property string|null $name
 * @property string|null $avatar_url
 * @property string|null $email
 * @property Carbon|null $email_verified_at
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property string|null $access_token_expires_at
 * @property Carbon|null $needs_relink_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SocialProvider|null $provider
 * @property-read User $user
 *
 * @method static \Database\Factories\LinkedAccountFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkedAccount newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkedAccount newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkedAccount query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkedAccount whereAccessToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkedAccount whereAccessTokenExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkedAccount whereAvatarUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkedAccount whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkedAccount whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkedAccount whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkedAccount whereExternalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkedAccount whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkedAccount whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkedAccount whereRefreshToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkedAccount whereSocialProviderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkedAccount whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkedAccount whereUserId($value)
 *
 * @mixin \Eloquent
 */
class LinkedAccount extends Model
{
    /** @use HasFactory<LinkedAccountFactory> */
    use HasFactory;

    use ToString;

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected $casts = [
        'refresh_token_expires_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'access_token_expires_at' => 'datetime',
        'needs_relink_at' => 'datetime',
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<SocialProvider, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(SocialProvider::class, 'social_provider_id');
    }

    public function canDelete(): bool
    {
        // A user must have 1 linked account for auth, regardless!
        if ($this->user->accounts()->count() === 1) {
            return false;
        }

        // If this isn't for auth, it can be deleted
        if (! $this->provider?->auth_enabled) {
            return true;
        }

        // If it is for auth, they must have at least one other auth
        return $this->user->accounts()->whereHas('provider', function ($query) {
            $query->where('auth_enabled', true);
        })->count() > 1;
    }

    public function needsRelink(): bool
    {
        return $this->needs_relink_at !== null;
    }
}
