<?php

namespace App\Services\Requests;

use App\Services\Music\Data\Track;

interface RequestRule
{
    public function decide(RequestContext $context, Track $track): RequestDecision;
}
