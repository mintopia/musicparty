<?php

namespace App\Mods;

use App\Enums\SettingType;

final readonly class SettingDefinition
{
    public function __construct(
        public string $code,
        public string $name,
        public SettingType $type,
        public mixed $default = null,
        public ?string $description = null,
        public bool $private = false,
        public bool $encrypted = false,
    ) {}
}
