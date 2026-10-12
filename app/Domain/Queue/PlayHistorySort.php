<?php

namespace App\Domain\Queue;

enum PlayHistorySort: string
{
    case Name = 'name';
    case Artist = 'artist';
    case Requested = 'requested';
    case Played = 'played';
    case Score = 'score';

    public function column(): string
    {
        return match ($this) {
            self::Name => 'title',
            self::Artist => 'artists',
            self::Requested => 'requested_at',
            self::Played => 'played_at',
            self::Score => 'score',
        };
    }
}
