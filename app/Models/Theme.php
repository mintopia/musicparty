<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property bool $readonly
 * @property bool $active
 * @property string $primary
 * @property bool $dark_mode
 * @property string $nav_background
 * @property string|null $css
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereCss($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereDarkMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereNavBackground($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme wherePrimary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereReadonly($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Theme extends Model
{
    use HasFactory;

    protected $casts = [
        'readonly' => 'boolean',
        'active' => 'boolean',
        'dark_mode' => 'boolean',
    ];

    public function rgb(string $property): string
    {
        $colour = $this->{$property};
        if (! preg_match('/^#(?:(?:[0-9a-f]{3}){1,2}|(?:[0-9a-f]{4}){1,2})$/i', $colour)) {
            return '0, 0, 0';
        }

        $hex = str_replace('#', '', $colour);
        if (strlen($hex) === 3) {
            $hex = str_repeat(substr($hex, 0, 1), 2).str_repeat(substr($hex, 1, 1), 2).str_repeat(substr($hex, 2, 1), 2);
        }
        $rgb = [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];

        return implode(', ', $rgb);
    }
}
