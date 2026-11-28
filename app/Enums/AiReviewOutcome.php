<?php

namespace App\Enums;

enum AiReviewOutcome: string
{
    case Allow = 'allow';
    case Flag = 'flag';
    case Reject = 'reject';
    case Error = 'error';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Allow => 'Allowed',
            self::Flag => 'Flagged',
            self::Reject => 'Rejected',
            self::Error => 'Error',
            self::Skipped => 'Skipped',
        };
    }
}
