<?php

namespace App\Models;

use App\Models\Traits\ToString;
use App\Services\Contracts\SocialProviderContract;
use Database\Factories\SocialProviderFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use stdClass;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string $provider_class
 * @property bool $supports_auth
 * @property bool $enabled
 * @property bool $auth_enabled
 * @property bool $can_be_renamed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, LinkedAccount> $accounts
 * @property-read int|null $accounts_count
 * @property-read Collection<int, ProviderSetting> $settings
 * @property-read int|null $settings_count
 *
 * @method static \Database\Factories\SocialProviderFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialProvider newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialProvider newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialProvider query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialProvider whereAuthEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialProvider whereCanBeRenamed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialProvider whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialProvider whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialProvider whereEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialProvider whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialProvider whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialProvider whereProviderClass($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialProvider whereSupportsAuth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialProvider whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class SocialProvider extends Model
{
    /** @use HasFactory<SocialProviderFactory> */
    use HasFactory;

    use ToString;

    /** @var array<string, mixed> */
    protected array $_settings = [];

    protected $casts = [
        'supports_auth' => 'boolean',
        'enabled' => 'boolean',
        'auth_enabled' => 'boolean',
        'can_be_renamed' => 'boolean',
    ];

    /** @return HasMany<LinkedAccount, $this> */
    public function accounts(): HasMany
    {
        return $this->hasMany(LinkedAccount::class);
    }

    public function redirect(?string $redirectUrl = null): RedirectResponse
    {
        return $this->getProvider($redirectUrl)->redirect();
    }

    public function getProvider(?string $redirectUrl = null): SocialProviderContract
    {
        /** @var class-string<SocialProviderContract> $providerClass */
        $providerClass = $this->provider_class;

        return new $providerClass($this, $redirectUrl);
    }

    public function user(?string $redirectUrl = null): mixed
    {
        return $this->getProvider($redirectUrl)->user();
    }

    /** @return array<string, stdClass> */
    public function configMapping(): array
    {
        return $this->getProvider()->configMapping();
    }

    protected function toStringName(): string
    {
        return $this->code;
    }

    /** @return MorphMany<ProviderSetting, $this> */
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
        if (! $setting) {
            $this->_settings[$code] = null;

            return null;
        }
        $this->_settings[$code] = $setting->value;

        return $setting->value;
    }
}
