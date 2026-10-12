<?php

namespace App\Domain\Queue;

enum PlayHistoryType: string
{
    case All = 'all';
    case Played = 'played';
    case Unsent = 'unsent';
}
