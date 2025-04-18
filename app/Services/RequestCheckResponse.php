<?php

namespace App\Services;

class RequestCheckResponse
{
    public function __construct(public bool $allowed, public ?string $reason = null)
    {
    }
}
