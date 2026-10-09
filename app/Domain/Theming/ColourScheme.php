<?php

namespace App\Domain\Theming;

enum ColourScheme: string
{
    case Light = 'light';
    case Dark = 'dark';
    case System = 'system';
}
