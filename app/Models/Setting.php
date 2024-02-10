<?php

namespace App\Models;

use App\Casts\SettingValue;
use App\Enums\SettingType;
use App\Models\Traits\ToString;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

/**
 * @mixin IdeHelperSetting
 */
class Setting extends Model implements Sortable
{
    use HasFactory, SortableTrait, ToString;

    protected static array $cached = [];

    protected $casts = [
        'value' => SettingValue::class,
        'type' => SettingType::class,
    ];

    public static function fetch(string $code, $default = null)
    {
        if (isset(static::$cached[$code])) {
            Log::debug("Fetched settings.{$code} from setting cache");
            return static::$cached[$code];
        }
        $key = "settings.{$code}";
        if ($setting = Cache::get($key)) {
            Log::debug("Fetched {$key} from application cache");
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
        Log::debug("Fetching {$key} from database");
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

    public function getValue()
    {
        return (object)[
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
