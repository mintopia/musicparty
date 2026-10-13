<?php

namespace App\Services\Requests\Rules;

use App\Services\Music\Data\Track;
use App\Services\Requests\RequestContext;
use App\Services\Requests\RequestDecision;
use App\Services\Requests\RequestRule;

final class MinLengthRule implements RequestRule
{
    public function decide(RequestContext $context, Track $track): RequestDecision
    {
        $min = $context->party->min_song_length;

        return $min && ($track->durationMs / 1000) < $min ? RequestDecision::reject('Song is too short') : RequestDecision::allow();
    }
}
