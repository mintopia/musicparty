<?php

namespace App\Exceptions;

use Throwable;

/**
 * Thrown by a Music Provider when the owner's linked account must be re-linked before it can be used.
 */
interface ProviderRelinkRequiredException extends Throwable {}
