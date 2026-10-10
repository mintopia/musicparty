<?php

namespace App\Domain\Playback;

enum PlayerHealth: string
{
    case Connected = 'connected';
    case Stale = 'stale';
    case Disconnected = 'disconnected';
}
