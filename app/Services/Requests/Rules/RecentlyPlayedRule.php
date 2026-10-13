<?php

namespace App\Services\Requests\Rules;

use App\Services\Music\Data\Track;
use App\Services\Requests\RequestContext;
use App\Services\Requests\RequestDecision;
use App\Services\Requests\RequestRule;

final class RecentlyPlayedRule implements RequestRule
{
    public function decide(RequestContext $context, Track $track): RequestDecision
    {
        $songId = $context->knownSongIds()[$track->id] ?? null;
        $interval = $context->party->no_repeat_interval;
        if ($songId === null || ! $interval) {
            return RequestDecision::allow();
        }

        $played = $context->remember('recently-played', fn (): array => $this->recentSongIds($context, $interval));

        return isset($played[$songId]) ? RequestDecision::reject('This song has been played recently') : RequestDecision::allow();
    }

    /**
     * @return array<int, true>
     */
    protected function recentSongIds(RequestContext $context, int $interval): array
    {
        $cutoff = now()->subSeconds($interval);
        $ids = $context->party->history()
            ->where('played_at', '>=', $cutoff)
            ->whereIn('song_id', array_values($context->knownSongIds()))
            ->pluck('song_id');

        return array_fill_keys($ids->all(), true);
    }
}
