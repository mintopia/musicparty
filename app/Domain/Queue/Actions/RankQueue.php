<?php

namespace App\Domain\Queue\Actions;

use App\Domain\Mod\Actions\ApplyScoreModifiers;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Data\RankedRequests;
use App\Domain\Queue\Models\TrackRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

readonly class RankQueue
{
    public function __construct(private ApplyScoreModifiers $modifiers) {}

    /**
     * Orders by Score (votes plus Score Modifier adjustments), then request time, then id.
     * Each returned Request carries its effective Score in `score`.
     *
     * @param  Builder<TrackRequest>  $query
     */
    public function __invoke(Party $party, Builder $query): RankedRequests
    {
        $requests = $query
            ->withSum('votes as score', 'value')
            ->orderByRaw('COALESCE(score, 0) desc')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $adjustments = ($this->modifiers)($party, $requests);

        return new RankedRequests($this->withEffectiveScores($requests, $adjustments), $adjustments);
    }

    /**
     * @param  Collection<int, TrackRequest>  $requests  ordered by vote score descending
     * @param  array<int, array<string, array{name: string, value: int}>>  $adjustments
     * @return Collection<int, TrackRequest>
     */
    private function withEffectiveScores(Collection $requests, array $adjustments): Collection
    {
        if ($adjustments === []) {
            return $requests;
        }

        foreach ($requests as $request) {
            $request->score = (int) $request->score + array_sum(array_column($adjustments[$request->id] ?? [], 'value'));
            $request->syncOriginalAttribute('score');
        }

        return $requests
            ->sortBy([
                fn (TrackRequest $a, TrackRequest $b): int => (int) $b->score <=> (int) $a->score,
                fn (TrackRequest $a, TrackRequest $b): int => $a->created_at <=> $b->created_at,
                fn (TrackRequest $a, TrackRequest $b): int => $a->id <=> $b->id,
            ])
            ->values();
    }
}
