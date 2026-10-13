<?php

namespace App\Services\Requests;

final readonly class RequestDecision
{
    public function __construct(public bool $allowed, public ?string $reason = null) {}

    public static function allow(): self
    {
        return new self(true);
    }

    public static function reject(string $reason): self
    {
        return new self(false, $reason);
    }
}
