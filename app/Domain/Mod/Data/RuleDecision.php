<?php

namespace App\Domain\Mod\Data;

use App\Domain\Mod\RuleOutcome;

final readonly class RuleDecision
{
    /**
     * @param  list<array{mod: EnabledMod, error: string}>  $failures
     */
    public function __construct(
        public RuleOutcome $outcome,
        public ?EnabledMod $decidedBy = null,
        public ?string $reason = null,
        public array $failures = [],
    ) {}
}
