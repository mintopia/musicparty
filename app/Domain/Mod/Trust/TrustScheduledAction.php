<?php

namespace App\Domain\Mod\Trust;

use App\Domain\Mod\Contracts\ScheduledAction;
use App\Domain\Mod\Data\ModContext;

readonly class TrustScheduledAction implements ScheduledAction
{
    public function __construct(private CalculateTrustScores $calculate) {}

    public function everySeconds(): int
    {
        return 3600;
    }

    public function run(ModContext $context): array
    {
        ($this->calculate)($context->party, new TrustSettings($context->settings));

        return [];
    }
}
