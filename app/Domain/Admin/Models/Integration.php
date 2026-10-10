<?php

namespace App\Domain\Admin\Models;

use App\Domain\Identity\Models\User;
use Database\Factories\IntegrationFactory;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

#[Unguarded]
#[UseFactory(IntegrationFactory::class)]
class Integration extends Model
{
    use HasApiTokens;

    /** @use HasFactory<IntegrationFactory> */
    use HasFactory;

    protected $casts = [
        'revoked_at' => 'datetime',
    ];

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * @return list<string>
     */
    public function abilities(): array
    {
        /** @var list<string> */
        return $this->tokens->flatMap(fn ($token): array => $token->abilities ?? [])->unique()->values()->all();
    }

    public function lastUsedAt(): ?Carbon
    {
        $latest = $this->tokens->max('last_used_at');

        return $latest === null ? null : Carbon::parse($latest);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
