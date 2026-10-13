<?php

namespace App\Services\Requests\Rules;

use App\Enums\PartyModerationAction;
use App\Services\Music\Data\Track;
use App\Services\Requests\RequestContext;
use App\Services\Requests\RequestDecision;
use App\Services\Requests\RequestRule;

final class ModerationRule implements RequestRule
{
    public function decide(RequestContext $context, Track $track): RequestDecision
    {
        foreach ($context->party->moderations as $moderation) {
            if ($moderation->match($track)) {
                return $moderation->action === PartyModerationAction::Flag
                    ? RequestDecision::flag($moderation->getMessage())
                    : RequestDecision::reject($moderation->getMessage());
            }
        }

        return RequestDecision::allow();
    }
}
