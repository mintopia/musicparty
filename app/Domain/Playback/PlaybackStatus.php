<?php

namespace App\Domain\Playback;

enum PlaybackStatus: string
{
    case Playing = 'playing';
    case Paused = 'paused';
    case Stopped = 'stopped';
}
