<?php

namespace App\Domain\Queue\Exceptions;

class RatingRefusedException extends RequestRefusedException
{
    public const NOT_PLAYING = 409;

    public static function notPlaying(): self
    {
        return new self('Only the track that is playing now can be rated.', self::NOT_PLAYING);
    }
}
