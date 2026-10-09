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

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Pending => in_array($next, [self::Queued, self::Rejected, self::Removed], true),
            self::Queued => in_array($next, [self::UpNext, self::Rejected, self::Removed], true),
            self::UpNext => in_array($next, [self::Playing, self::Removed], true),
            self::Playing => $next === self::Played,
            self::Played, self::Rejected, self::Removed => false,
        };
    }
}
