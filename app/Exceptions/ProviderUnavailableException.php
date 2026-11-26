<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown by a Music Provider when it could not be reached or did not answer in time.
 */
class ProviderUnavailableException extends RuntimeException
{
    public function __construct(public readonly bool $timedOut, ?Throwable $previous = null)
    {
        parent::__construct($timedOut ? 'The music provider timed out' : 'The music provider could not be reached', 0, $previous);
    }
}
