<?php

namespace App\Domain\Music\Data;

use SensitiveParameter;

final readonly class HostAccountLinkData
{
    public function __construct(
        public string $externalId,
        public ?string $name,
        #[SensitiveParameter] public string $accessToken,
        #[SensitiveParameter] public ?string $refreshToken,
        public ?int $expiresIn,
    ) {}
}
