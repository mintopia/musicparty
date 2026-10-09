<?php

namespace App\Domain\Identity\Exceptions;

use RuntimeException;

class LoginRefusedException extends RuntimeException
{
    public static function providerUnavailable(string $code): self
    {
        return new self("Login with {$code} is not available.");
    }

    public static function suspended(): self
    {
        return new self('Your account has been suspended.');
    }
}
