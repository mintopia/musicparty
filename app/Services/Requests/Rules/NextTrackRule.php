<?php

namespace App\Services\Requests\Rules;

use App\Services\Music\Data\Track;
use App\Services\Requests\RequestContext;
use App\Services\Requests\RequestDecision;
use App\Services\Requests\RequestRule;

final class NextTrackRule implements RequestRule
{
    public function decide(RequestContext $context, Track $track): RequestDecision
    {
        $songId = $context->knownSongIds()[$track->id] ?? null;
        if ($songId === null) {
            return RequestDecision::allow();
        }

        $nextSongId = $context->remember('next-song-id', fn (): ?int => $context->party->next()?->song_id);

        return $nextSongId === $songId ? RequestDecision::reject('This song will be played next') : RequestDecision::allow();
    }
}
