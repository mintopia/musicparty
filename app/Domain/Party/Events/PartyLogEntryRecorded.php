<?php

namespace App\Domain\Party\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class PartyLogEntryRecorded implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public string $partyCode,
        public int $entryId,
        public string $action,
        public ?string $subject,
    ) {}
}
