<?php

namespace App\Domain\Playback\Exceptions;

use RuntimeException;

class IncompatibleProviderException extends RuntimeException
{
    /**
     * @param  list<string>  $compatibleProviders
     */
    public static function for(string $playerKind, string $providerId, array $compatibleProviders): self
    {
        $compatible = $compatibleProviders === [] ? 'none' : implode(', ', $compatibleProviders);

        return new self("Player '{$playerKind}' is not compatible with Music Provider '{$providerId}'. Compatible: {$compatible}.");
    }
}
