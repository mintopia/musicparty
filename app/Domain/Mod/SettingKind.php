<?php

namespace App\Domain\Mod;

enum SettingKind: string
{
    case Boolean = 'boolean';
    case Integer = 'integer';
    case Text = 'text';
    case Choice = 'choice';
    case Secret = 'secret';
}
