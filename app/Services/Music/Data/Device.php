<?php

namespace App\Services\Music\Data;

final readonly class Device
{
    public function __construct(
        public ?string $id,
        public string $name,
        public string $type,
        public bool $isActive,
    ) {}
}
