<?php

namespace App\Domain\Mod\Data;

use App\Domain\Mod\Contracts\Mod;

final readonly class EnabledMod
{
    public function __construct(public Mod $mod, public ModContext $context) {}

    public function systemActor(): string
    {
        return 'mod:'.$this->mod->id();
    }
}
