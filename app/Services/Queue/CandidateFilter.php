<?php

namespace App\Services\Queue;

use App\Models\UpcomingSong;

interface CandidateFilter
{
    /**
     * Whether the Request may be selected for the Queue. Runs before the effective-Score step.
     */
    public function allows(UpcomingSong $request): bool;
}
