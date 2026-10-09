<?php

namespace App\Domain\Playback\Exceptions;

use App\Domain\Playback\Control;
use RuntimeException;

class UnsupportedControl extends RuntimeException
{
    public static function for(Control $control): self
    {
        return new self("{$control->value} is unsupported by this Player");
    }
}
