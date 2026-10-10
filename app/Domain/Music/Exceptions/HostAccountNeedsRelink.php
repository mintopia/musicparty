<?php

namespace App\Domain\Music\Exceptions;

class HostAccountNeedsRelink extends ProviderUnavailableException
{
    public static function forAccount(int|string $accountId): self
    {
        return new self("Host account {$accountId} must be relinked to Spotify.");
    }
}
