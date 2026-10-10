<?php

namespace App\Domain\Mod;

enum DecorationAccent: string
{
    case Accent = 'accent';
    case Success = 'success';
    case Warning = 'warning';
    case Danger = 'danger';
    case Info = 'info';
    case Muted = 'muted';
}
