<?php

namespace App\Services\Requests\Rules;

use App\Services\Music\Data\Track;
use App\Services\Requests\RequestContext;
use App\Services\Requests\RequestDecision;
use App\Services\Requests\RequestRule;

final class AcceptingRequestsRule implements RequestRule
{
    public function decide(RequestContext $context, Track $track): RequestDecision
    {
        return $context->party->allow_requests ? RequestDecision::allow() : RequestDecision::reject('Party is not accepting requests');
    }
}
