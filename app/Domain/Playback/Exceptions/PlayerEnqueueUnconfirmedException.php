<?php

namespace App\Domain\Playback\Exceptions;

use RuntimeException;

/**
 * The enqueue may or may not have reached the Player, so it must not be sent again until the Player's state says.
 */
class PlayerEnqueueUnconfirmedException extends RuntimeException
{
    public function __construct(string $message = 'The Player may have received the enqueue.')
    {
        parent::__construct($message);
    }
}
