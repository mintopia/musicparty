<?php

namespace App\Enums;

enum ScheduleFrequency: string
{
    case Minute = 'minute';
    case Hour = 'hour';
    case Day = 'day';
}
