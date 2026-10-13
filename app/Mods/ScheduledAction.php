<?php

namespace App\Mods;

use App\Enums\ScheduleFrequency;
use App\Models\Party;
use Closure;

final readonly class ScheduledAction
{
    /**
     * @param  Closure(Party): void  $handler
     */
    public function __construct(
        public string $code,
        public ScheduleFrequency $frequency,
        public Closure $handler,
    ) {}
}
