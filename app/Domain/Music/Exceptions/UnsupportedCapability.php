<?php

namespace App\Domain\Music\Exceptions;

use App\Domain\Music\Capability;
use RuntimeException;

class UnsupportedCapability extends RuntimeException
{
    public static function for(string $providerId, Capability $capability): self
    {
        return new self("{$capability->value} is unsupported by Music Provider '{$providerId}'.");
    }
}
