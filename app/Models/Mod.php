<?php

namespace App\Models;

use Database\Factories\ModFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, ModSetting> $settings
 * @property-read int|null $settings_count
 *
 * @method static \Database\Factories\ModFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mod newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mod newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mod query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mod whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mod whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mod whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mod whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mod whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mod whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Mod extends Model
{
    /** @use HasFactory<ModFactory> */
    use HasFactory;

    /** @return HasMany<ModSetting, $this> */
    public function settings(): HasMany
    {
        return $this->hasMany(ModSetting::class);
    }
}
