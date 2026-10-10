<?php

namespace App\Domain\Admin\Models;

use App\Domain\Identity\Models\User;
use Database\Factories\IntegrationTokenFactory;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Unguarded]
#[UseFactory(IntegrationTokenFactory::class)]
class IntegrationToken extends Model implements AuthenticatableContract
{
    /** @use HasFactory<IntegrationTokenFactory> */
    use Authenticatable, HasFactory;

    protected $hidden = ['token_hash'];

    protected $casts = [
        'abilities' => 'array',
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public static function hashFor(string $plainText): string
    {
        return hash('sha256', $plainText);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function can(string $ability): bool
    {
        return in_array($ability, $this->abilities, true);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
