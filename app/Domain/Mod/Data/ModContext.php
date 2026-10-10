<?php

namespace App\Domain\Mod\Data;

use App\Domain\Party\Models\Party;

final readonly class ModContext
{
    /**
     * @param  array<string, mixed>  $settings  validated settings with defaults applied, secrets decrypted
     */
    public function __construct(
        public string $modId,
        public Party $party,
        public array $settings,
    ) {}
}
