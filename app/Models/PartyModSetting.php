<?php

namespace App\Models;

use App\Casts\SettingValue;
use App\Events\Mods\PartyModSettingDeletedEvent;
use App\Events\Mods\PartyModSettingUpdatedEvent;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperPartyModSetting
 */
class PartyModSetting extends Model
{
    use HasFactory;

    protected $casts = [
        'value' => SettingValue::class,
    ];

    protected $dispatchesEvents = [
        'saved' => PartyModSettingUpdatedEvent::class,
        'deleted' => PartyModSettingDeletedEvent::class,
    ];

    protected function getType(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => $this->setting->type
        );
    }

    public function setting(): BelongsTo
    {
        return $this->belongsTo(ModSetting::class, 'mod_setting_id');
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
