<?php

namespace App\Domain\Identity\Exceptions;

use RuntimeException;

class AccountLinkRefusedException extends RuntimeException
{
    public static function providerUnavailable(string $name): self
    {
        return new self("Linking a {$name} account is not available.");
    }

    public static function ownedByAnotherUser(): self
    {
        return new self('That account is already associated with another user.');
    }

    public static function providerAlreadyLinked(string $name): self
    {
        return new self("You already have a different {$name} account linked.");
    }
}
