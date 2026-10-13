<?php

namespace App\Services\Requests\Rules;

use App\Services\Music\Data\Track;
use App\Services\Requests\RequestContext;
use App\Services\Requests\RequestDecision;
use App\Services\Requests\RequestRule;

final class BannedMemberRule implements RequestRule
{
    public function decide(RequestContext $context, Track $track): RequestDecision
    {
        return $context->member->role->code === 'banned' ? RequestDecision::reject('You are not allowed to make requests') : RequestDecision::allow();
    }
}
