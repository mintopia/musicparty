<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Mod-owned data attached to a core model, so core tables never carry mod-only columns.
 *
 * @property int $id
 * @property string $mod_code
 * @property string $owner_type
 * @property int $owner_id
 * @property string $key
 * @property mixed $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model $owner
 *
 * @mixin \Eloquent
 */
class ModData extends Model
{
    protected $table = 'mod_data';

    protected $guarded = [];

    protected $casts = [
        'value' => 'json',
    ];

    /** @return MorphTo<Model, $this> */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }
}
