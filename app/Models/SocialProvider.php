<?php

namespace App\Models;

use App\Models\Traits\ToString;
use App\Services\Contracts\SocialProviderContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @mixin IdeHelperSocialProvider
 */
class SocialProvider extends Model
{
    use HasFactory, ToString;

    protected array $_settings = [];

    public function accounts(): HasMany
    {
        return $this->hasMany(LinkedAccount::class);
    }

    public function redirect(?string $redirectUrl = null)
    {
        return $this->getProvider($redirectUrl)->redirect();
    }

    public function getProvider(?string $redirectUrl = null): SocialProviderContract
    {
        return new $this->provider_class($this, $redirectUrl);
    }

    public function user(?string $redirectUrl = null)
    {
        return $this->getProvider($redirectUrl)->user();
    }

    public function configMapping(): array
    {
        return $this->getProvider()->configMapping();
    }

    protected function toStringName(): string
    {
        return $this->code;
    }

    public function settings(): MorphMany
    {
        return $this->morphMany(ProviderSetting::class, 'provider');
    }

    public function getSetting(string $code): mixed
    {
        if (isset($this->_settings[$code])) {
            return $this->_settings[$code];
        }
        $setting = $this->settings()->whereCode($code)->first();
        if (!$setting) {
            $this->_settings[$code] = null;
            return null;
        }
        $this->_settings[$code] = $setting->value;
        return $setting->value;
    }
}
