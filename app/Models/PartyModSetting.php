<?php

namespace App\Models;

use App\Casts\SettingValue;
use App\Enums\SettingType;
use App\Events\Mods\PartyModSettingDeletedEvent;
use App\Events\Mods\PartyModSettingUpdatedEvent;
use Database\Factories\PartyModSettingFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $party_id
 * @property int $mod_setting_id
 * @property mixed|null $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read mixed $get_type
 * @property-read Party $party
 * @property-read ModSetting $setting
 *
 * @method static \Database\Factories\PartyModSettingFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyModSetting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyModSetting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyModSetting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyModSetting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyModSetting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyModSetting whereModSettingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyModSetting wherePartyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyModSetting whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyModSetting whereValue($value)
 *
 * @mixin \Eloquent
 */
class PartyModSetting extends Model
{
    /** @use HasFactory<PartyModSettingFactory> */
    use HasFactory;

    protected $casts = [
        'value' => SettingValue::class,
    ];

    protected $dispatchesEvents = [
        'saved' => PartyModSettingUpdatedEvent::class,
        'deleted' => PartyModSettingDeletedEvent::class,
    ];

    /** @return Attribute<SettingType, never> */
    protected function getType(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => $this->setting->type
        );
    }

    /** @return BelongsTo<ModSetting, $this> */
    public function setting(): BelongsTo
    {
        return $this->belongsTo(ModSetting::class, 'mod_setting_id');
    }

    /** @return BelongsTo<Party, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
