<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperVote
 */
class Vote extends Model
{
    use HasFactory;

    public function upcomingSong(): BelongsTo
    {
        return $this->belongsTo(UpcomingSong::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
