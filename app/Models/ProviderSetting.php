<?php

namespace App\Models;

use App\Casts\SettingValue;
use App\Enums\SettingType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

/**
 * @property int $id
 * @property string $provider_type
 * @property int $provider_id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property SettingType $type
 * @property bool $encrypted
 * @property string|null $validation
 * @property mixed|null $value
 * @property int $order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model $provider
 *
 * @method static Builder<static>|ProviderSetting newModelQuery()
 * @method static Builder<static>|ProviderSetting newQuery()
 * @method static Builder<static>|ProviderSetting ordered(string $direction = 'asc')
 * @method static Builder<static>|ProviderSetting query()
 * @method static Builder<static>|ProviderSetting whereCode($value)
 * @method static Builder<static>|ProviderSetting whereCreatedAt($value)
 * @method static Builder<static>|ProviderSetting whereDescription($value)
 * @method static Builder<static>|ProviderSetting whereEncrypted($value)
 * @method static Builder<static>|ProviderSetting whereId($value)
 * @method static Builder<static>|ProviderSetting whereName($value)
 * @method static Builder<static>|ProviderSetting whereOrder($value)
 * @method static Builder<static>|ProviderSetting whereProviderId($value)
 * @method static Builder<static>|ProviderSetting whereProviderType($value)
 * @method static Builder<static>|ProviderSetting whereType($value)
 * @method static Builder<static>|ProviderSetting whereUpdatedAt($value)
 * @method static Builder<static>|ProviderSetting whereValidation($value)
 * @method static Builder<static>|ProviderSetting whereValue($value)
 *
 * @mixin \Eloquent
 */
class ProviderSetting extends Model implements Sortable
{
    /** @use HasFactory<Factory<ProviderSetting>> */
    use HasFactory;

    use SortableTrait;

    protected $casts = [
        'encrypted' => 'boolean',
        'value' => SettingValue::class,
        'type' => SettingType::class,
    ];

    /** @return Builder<ProviderSetting> */
    public function buildSortQuery(): Builder
    {
        return self::where('provider_id', $this->provider_id)->where('provider_type', $this->provider_type);
    }

    /** @return MorphTo<Model, $this> */
    public function provider(): MorphTo
    {
        return $this->morphTo();
    }

    public function isRequired(): bool
    {
        return str_contains($this->validation ?? '', 'required');
    }
}
