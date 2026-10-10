<?php

namespace App\Domain\Queue;

enum PlayHistoryType: string
{
    case Sent = 'sent';
    case Requested = 'requested';
    case Fallback = 'fallback';
}
