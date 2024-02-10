<?php

namespace App\Models;

use App\Models\Traits\ToString;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperLinkedAccount
 */
class LinkedAccount extends Model
{
    use HasFactory, ToString;

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected $casts = [
        'refresh_token_expires_at' => 'datetime',
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

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
        if (!$this->provider->auth_enabled) {
            return true;
        }

        // If it is for auth, they must have at least one other auth
        return $this->user->accounts()->whereHas('provider', function ($query) {
                $query->where('auth_enabled', true);
            })->count() > 1;
    }
}
