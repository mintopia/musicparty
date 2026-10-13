<?php

namespace App\Models;

use App\Casts\SettingValue;
use App\Enums\SettingType;
use App\Models\Traits\ToString;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property bool $encrypted
 * @property bool $hidden
 * @property mixed|null $value
 * @property string|null $validation
 * @property SettingType $type
 * @property int $order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting ordered(string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereEncrypted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereHidden($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereValidation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereValue($value)
 *
 * @mixin \Eloquent
 */
class Setting extends Model implements Sortable
{
    /** @use HasFactory<Factory<Setting>> */
    use HasFactory;

    use SortableTrait;
    use ToString;

    /** @var array<string, mixed> */
    protected static array $cached = [];

    protected $casts = [
        'encrypted' => 'boolean',
        'hidden' => 'boolean',
        'value' => SettingValue::class,
        'type' => SettingType::class,
    ];

    public static function fetch(string $code, mixed $default = null): mixed
    {
        if (isset(static::$cached[$code])) {
            return static::$cached[$code];
        }
        $key = "settings.{$code}";
        if ($setting = Cache::get($key)) {
            if ($setting->value === null) {
                return $default;
            }
            if ($setting->encrypted) {
                static::$cached[$code] = $setting->value;

                return Crypt::decrypt($setting->value);
            }
            static::$cached[$code] = $setting->value;

            return $setting->value;
        }
        $setting = Setting::whereCode($code)->first();
        if ($setting === null) {
            Cache::put($key, $setting);
            static::$cached[$code] = null;

            return $default;
        }
        Cache::put($key, $setting->getValue());
        static::$cached[$code] = $setting->value;

        return $setting->value ?? $default;
    }

    public function clearCache(): void
    {
        Log::debug("Clearing settings.{$this->code} from cache");
        unset(static::$cached[$this->code]);
        Cache::forget("settings.{$this->code}");
    }

    /** @return object{code: string, encrypted: bool, value: mixed} */
    public function getValue(): object
    {
        return (object) [
            'code' => $this->code,
            'encrypted' => $this->encrypted,
            'value' => $this->encrypted ? Crypt::encrypt($this->value) : $this->value,
        ];
    }

    protected function toStringName(): string
    {
        return $this->code;
    }
}
