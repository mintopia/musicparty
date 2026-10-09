<?php

namespace App\Domain\Playback\Exceptions;

use RuntimeException;

class PlayerDisconnectedException extends RuntimeException
{
    public function __construct(string $message = 'The Player is disconnected.')
    {
        parent::__construct($message);
    }
}
