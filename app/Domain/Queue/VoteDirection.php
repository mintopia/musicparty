<?php

namespace App\Domain\Queue;

enum VoteDirection: string
{
    case Up = 'up';
    case Down = 'down';

    public function weight(): int
    {
        return $this === self::Up ? 1 : -1;
    }
}
