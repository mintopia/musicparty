<?php

namespace App\Domain\Playback\Exceptions;

use RuntimeException;

/**
 * The Music Provider understood a playback command but refused it, e.g. no active device or a playback restriction.
 */
class PlayerCommandRejectedException extends RuntimeException {}
