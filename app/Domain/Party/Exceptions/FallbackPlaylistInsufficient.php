<?php

namespace App\Domain\Party\Exceptions;

use App\Domain\Party\FallbackPlaylistCheck;
use RuntimeException;

class FallbackPlaylistInsufficient extends RuntimeException
{
    public static function for(FallbackPlaylistCheck $check): self
    {
        return new self($check->message());
    }
}
