<?php

namespace App\Domain\Mod\Data;

use App\Domain\Mod\SettingKind;

final readonly class SettingDefinition
{
    /**
     * @param  list<string>|null  $options  allowed values for SettingKind::Choice
     */
    public function __construct(
        public string $key,
        public string $label,
        public SettingKind $kind,
        public mixed $default = null,
        public ?int $min = null,
        public ?int $max = null,
        public ?array $options = null,
        public bool $required = false,
    ) {}
}
