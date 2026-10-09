<?php

namespace App\Domain\Queue;

enum RequestStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case UpNext = 'up_next';
    case Playing = 'playing';
    case Played = 'played';
    case Rejected = 'rejected';
    case Removed = 'removed';
}
