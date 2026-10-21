<?php

namespace App\Enums;

enum SettingType
{
    case stString;
    case stBoolean;
    case stInteger;
    case stFloat;
    case stDateTime;
}
