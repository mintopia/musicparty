<?php

namespace App\Domain\Mod\Data;

use App\Domain\Mod\RuleOutcome;

final readonly class RuleVerdict
{
    private function __construct(public RuleOutcome $outcome, public ?string $reason) {}

    public static function accept(): self
    {
        return new self(RuleOutcome::Accept, null);
    }

    public static function hold(string $reason): self
    {
        return new self(RuleOutcome::Hold, $reason);
    }

    public static function reject(string $reason): self
    {
        return new self(RuleOutcome::Reject, $reason);
    }
}
