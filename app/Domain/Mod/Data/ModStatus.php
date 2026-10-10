<?php

namespace App\Domain\Mod\Data;

use App\Domain\Mod\Contracts\Mod;

final readonly class ModStatus
{
    /**
     * @param  list<SettingDefinition>  $definitions
     * @param  array<string, mixed>  $settings  current values with secrets masked
     */
    public function __construct(
        public Mod $mod,
        public bool $enabled,
        public array $definitions,
        public array $settings,
    ) {}
}
