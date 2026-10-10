<?php

namespace App\Domain\Music\Exceptions;

use RuntimeException;

class ProviderUnavailableException extends RuntimeException
{
    public static function forProvider(string $providerId): self
    {
        return new self("Music Provider '{$providerId}' is unavailable: no search credential is configured.");
    }

    public static function rejectedRefresh(string $providerId): self
    {
        return new self("Music Provider '{$providerId}' is unavailable: the token endpoint rejected the client credentials or request.");
    }
}
