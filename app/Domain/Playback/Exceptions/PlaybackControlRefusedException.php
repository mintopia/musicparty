<?php

namespace App\Domain\Playback\Exceptions;

use App\Domain\Playback\Control;
use RuntimeException;

class PlaybackControlRefusedException extends RuntimeException
{
    public const NO_PLAYER = 409;

    public const DISCONNECTED = 409;

    public const UNSUPPORTED = 422;

    public static function noPlayer(): self
    {
        return new self('No Player is paired with this party, so playback cannot be controlled.', self::NO_PLAYER);
    }

    public static function disconnected(): self
    {
        return new self('The Player is disconnected, so playback cannot be controlled.', self::DISCONNECTED);
    }

    public static function unsupported(Control $control): self
    {
        return new self("This Player does not support {$control->value}.", self::UNSUPPORTED);
    }

    public function status(): int
    {
        return $this->getCode();
    }
}
