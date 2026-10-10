<?php

namespace App\Domain\Mod\Contracts;

use App\Domain\Mod\Data\Decoration;
use App\Domain\Mod\Data\ModContext;
use App\Models\Play;
use App\Models\TrackRequest;

interface DecorationProvider
{
    /**
     * @return list<Decoration|array<string, mixed>>
     */
    public function decorate(ModContext $context, TrackRequest|Play $subject): array;
}
