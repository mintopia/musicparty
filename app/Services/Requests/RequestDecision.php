<?php

namespace App\Services\Requests;

final readonly class RequestDecision
{
    public bool $allowed;

    public function __construct(public RequestOutcome $outcome = RequestOutcome::Allow, public ?string $reason = null)
    {
        $this->allowed = $outcome !== RequestOutcome::Reject;
    }

    public static function allow(): self
    {
        return new self(RequestOutcome::Allow);
    }

    public static function flag(string $reason): self
    {
        return new self(RequestOutcome::Flag, $reason);
    }

    public static function reject(string $reason): self
    {
        return new self(RequestOutcome::Reject, $reason);
    }

    public function isFlagged(): bool
    {
        return $this->outcome === RequestOutcome::Flag;
    }
}
