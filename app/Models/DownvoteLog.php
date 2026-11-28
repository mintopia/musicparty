<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $party_id
 * @property int $user_id
 * @property Carbon|null $created_at
 */
#[Fillable(['party_id', 'user_id'])]
class DownvoteLog extends Model
{
    public const UPDATED_AT = null;
}
