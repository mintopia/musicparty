<?php

namespace App\Domain\Music\Exceptions;

use RuntimeException;

class AccountAlreadyLinkedException extends RuntimeException
{
    public function __construct(string $message = 'That Music Provider account is already linked to another user.')
    {
        parent::__construct($message);
    }
}
