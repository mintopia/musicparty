<?php

namespace App\Domain\Music\Exceptions;

use RuntimeException;

class NotHostException extends RuntimeException
{
    public function __construct(string $message = 'Only the Host of a Party can manage its Music Provider account.')
    {
        parent::__construct($message);
    }
}
