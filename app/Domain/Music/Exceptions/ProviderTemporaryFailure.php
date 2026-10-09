<?php

namespace App\Domain\Music\Exceptions;

use RuntimeException;

class ProviderTemporaryFailure extends RuntimeException
{
    public function __construct(
        string $message = 'The Music Provider is temporarily unavailable.',
        public readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct($message);
    }
}
