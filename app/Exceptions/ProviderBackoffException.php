<?php

namespace App\Exceptions;

use Throwable;

/**
 * Thrown by a Music Provider while it is rate limited and must not be called again yet.
 */
interface ProviderBackoffException extends Throwable
{
    public function retryAfterSeconds(): int;
}
