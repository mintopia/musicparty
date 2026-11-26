<?php

namespace App\Enums;

enum SettingType
{
    case stString;
    case stText;
    case stBoolean;
    case stInteger;
    case stFloat;
    case stDateTime;
    case stTrack;
    case stTimeOfDay;
    case stSecret;
    case stChoice;
    case stUser;

    public function label(): string
    {
        return match ($this) {
            self::stString => 'string',
            self::stText => 'text',
            self::stBoolean => 'boolean',
            self::stInteger => 'integer',
            self::stFloat => 'float',
            self::stDateTime => 'datetime',
            self::stTrack => 'track',
            self::stTimeOfDay => 'time_of_day',
            self::stSecret => 'secret',
            self::stChoice => 'choice',
            self::stUser => 'user',
        };
    }
}
