<?php

namespace App\Services\Requests\Rules;

use App\Services\Music\Data\Track;
use App\Services\Requests\RequestContext;
use App\Services\Requests\RequestDecision;
use App\Services\Requests\RequestRule;

final class ContextPlaylistTrackRule implements RequestRule
{
    public const TRACK_ID = '62mFjcWGkt2OY9LT9EvxS2';

    public function decide(RequestContext $context, Track $track): RequestDecision
    {
        return $track->id === self::TRACK_ID ? RequestDecision::reject('This song is not available') : RequestDecision::allow();
    }
}
