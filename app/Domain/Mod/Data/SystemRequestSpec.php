<?php

namespace App\Domain\Mod\Data;

final readonly class SystemRequestSpec
{
    public function __construct(
        public string $providerTrackId,
        public bool $bypassRules = false,
    ) {}
}
