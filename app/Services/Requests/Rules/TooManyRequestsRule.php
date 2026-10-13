<?php

namespace App\Services\Requests\Rules;

use App\Services\Music\Data\Track;
use App\Services\Requests\RequestContext;
use App\Services\Requests\RequestDecision;
use App\Services\Requests\RequestRule;

final class TooManyRequestsRule implements RequestRule
{
    public function decide(RequestContext $context, Track $track): RequestDecision
    {
        $exceeded = $context->remember('too-many-requests', fn (): bool => $this->exceedsLimit($context));

        return $exceeded ? RequestDecision::reject('You are not allowed to request any more songs') : RequestDecision::allow();
    }

    protected function exceedsLimit(RequestContext $context): bool
    {
        $party = $context->party;
        $member = $context->member;
        if ($party->max_requests === null || $party->canBeManagedBy($member->user) || $member->role->code === 'vip') {
            return false;
        }

        $waiting = $party->upcoming()->whereNull('queued_at')->whereUserId($member->user_id)->count();

        return $waiting >= $party->max_requests;
    }
}
