<?php

namespace App\Domain\Queue\Events;

use Illuminate\Foundation\Events\Dispatchable;

class RequestDecisionRecorded
{
    use Dispatchable;

    public function __construct(public int $partyId, public int $requestId, public string $status) {}
}
