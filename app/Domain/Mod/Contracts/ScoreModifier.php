<?php

namespace App\Domain\Mod\Contracts;

use App\Domain\Mod\Data\ModContext;
use App\Models\TrackRequest;

interface ScoreModifier
{
    public function adjustment(ModContext $context, TrackRequest $request): int;
}
