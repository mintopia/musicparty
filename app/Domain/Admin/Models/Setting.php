<?php

namespace App\Domain\Admin\Models;

use App\Casts\SettingValue;
use App\Enums\SettingType;
use App\Support\Concerns\ToString;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * @mixin IdeHelperSetting
 */
class Setting extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use ToString;

    protected $casts = [
        'value' => SettingValue::class,
        'type' => SettingType::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (Setting $setting): void {
            $setting->order ??= (int) static::query()->max('order') + 1;
        });
    }

    /**
     * @param  Builder<Setting>  $query
     * @return Builder<Setting>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order');
    }

    /**
     * @param  mixed  $default
     * @return mixed
     */
    public static function fetch(string $code, $default = null)
    {
        $key = "settings.{$code}";
        if ($cached = Cache::get($key)) {
            if ($cached->value === null) {
                return $default;
            }

            return $cached->encrypted ? Crypt::decrypt($cached->value) : $cached->value;
        }
        $setting = Setting::whereCode($code)->first();
        if ($setting === null) {
            return $default;
        }
        Cache::put($key, $setting->getValue());

        return $setting->value ?? $default;
    }

    public function clearCache(): void
    {
        Log::debug("Clearing settings.{$this->code} from cache");
        Cache::forget("settings.{$this->code}");
    }

    /**
     * @return object{code: string, encrypted: bool, value: mixed}
     */
    public function getValue()
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
