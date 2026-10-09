<?php

namespace App\Domain\Playback;

enum FeedMode: string
{
    case Ahead = 'ahead';
    case JustInTime = 'just-in-time';
}
