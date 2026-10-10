<?php

namespace App\Domain\Admin\Models;

use App\Domain\Identity\Models\User;
use Database\Factories\AdminAuditEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Unguarded]
#[UseFactory(AdminAuditEntryFactory::class)]
class AdminAuditEntry extends Model
{
    /** @use HasFactory<AdminAuditEntryFactory> */
    use HasFactory;

    protected $casts = ['meta' => 'array'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }
}
