<?php

namespace App\Enums;

enum SettingType
{
    case stString;
    case stBoolean;
    case stInteger;
    case stFloat;
    case stDateTime;
    case stTrack;
    case stTimeOfDay;
    case stSecret;

    public function label(): string
    {
        return match ($this) {
            self::stString => 'string',
            self::stBoolean => 'boolean',
            self::stInteger => 'integer',
            self::stFloat => 'float',
            self::stDateTime => 'datetime',
            self::stTrack => 'track',
            self::stTimeOfDay => 'time_of_day',
            self::stSecret => 'secret',
        };
    }
}
