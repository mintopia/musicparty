<?php

namespace App\Domain\Queue\Actions;

use App\Domain\Mod\Actions\ApplyScoreModifiers;
use App\Domain\Party\Actions\RecordPartyLogEntry;
use App\Domain\Party\Models\Party;
use App\Domain\Party\PartyState;
use App\Domain\Queue\Randomizer;
use App\Domain\Queue\RequestStatus;
use App\Domain\Queue\SelectionMode;
use App\Models\TrackRequest;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

readonly class SelectUpNext
{
    public function __construct(private RecordPartyLogEntry $record, private Randomizer $randomizer, private ApplyScoreModifiers $modifiers) {}

    /**
     * Returns the newly locked Up Next Request, or null when one already exists or none is eligible.
     */
    public function __invoke(Party $party): ?TrackRequest
    {
        try {
            return $this->select($party);
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    private function select(Party $party): ?TrackRequest
    {
        return DB::transaction(function () use ($party): ?TrackRequest {
            $locked = Party::query()->whereKey($party->id)->lockForUpdate()->firstOrFail();

            if ($locked->state !== PartyState::Live) {
                return null;
            }

            $hasUpNext = TrackRequest::query()
                ->where('party_id', $locked->id)
                ->where('status', RequestStatus::UpNext)
                ->exists();

            if ($hasUpNext) {
                return null;
            }

            $ranked = TrackRequest::query()
                ->where('party_id', $locked->id)
                ->where('status', RequestStatus::Queued)
                ->where(fn ($query) => $query->whereNull('not_before')->orWhere('not_before', '<=', now()))
                ->withSum('votes as score', 'value')
                ->orderByRaw('COALESCE(score, 0) desc')
                ->orderBy('created_at')
                ->orderBy('id');

            $mode = $locked->selection_mode;
            $eligible = $ranked->get();
            $adjustments = ($this->modifiers)($locked, $eligible);
            $eligible = $this->withEffectiveScores($eligible, $adjustments);
            $candidate = $mode === SelectionMode::Weighted ? $this->roulette($eligible) : $eligible->first();

            if ($candidate === null) {
                return null;
            }

            $score = (int) $candidate->score;

            $candidate->forceFill([
                'status' => RequestStatus::UpNext,
                'up_next_at' => now(),
                'enqueued_at' => null,
                'selection_mode' => $mode->value,
                'selection_score' => $score,
            ])->save();

            foreach ($adjustments[$candidate->id] ?? [] as $modId => $adjustment) {
                ($this->record)($locked, 'mod.score_adjusted', subject: $candidate->title, details: [
                    'mod' => $adjustment['name'],
                    'mod_id' => $modId,
                    'request_id' => $candidate->id,
                    'adjustment' => $adjustment['value'],
                    'score' => $score,
                ], systemActor: 'mod:'.$modId);
            }

            ($this->record)($locked, 'queue.selected', subject: $candidate->title, details: [
                'request_id' => $candidate->id,
                'mode' => $mode->value,
                'score' => $score,
                'fallback' => $candidate->party_member_id === null,
            ], systemActor: 'queue');

            return $candidate;
        });
    }

    /**
     * @param  Collection<int, TrackRequest>  $eligible  ordered by vote score descending
     * @param  array<int, array<string, array{name: string, value: int}>>  $adjustments
     * @return Collection<int, TrackRequest>
     */
    private function withEffectiveScores(Collection $eligible, array $adjustments): Collection
    {
        if ($adjustments === []) {
            return $eligible;
        }

        foreach ($eligible as $request) {
            $request->score = (int) $request->score + array_sum(array_column($adjustments[$request->id] ?? [], 'value'));
            $request->syncOriginalAttribute('score');
        }

        return $eligible
            ->sortBy([
                fn (TrackRequest $a, TrackRequest $b): int => (int) $b->score <=> (int) $a->score,
                fn (TrackRequest $a, TrackRequest $b): int => $a->created_at <=> $b->created_at,
                fn (TrackRequest $a, TrackRequest $b): int => $a->id <=> $b->id,
            ])
            ->values();
    }

    /**
     * Picks proportionally to positive score, falling back to the highest-ranked Request when none is positive.
     *
     * @param  Collection<int, TrackRequest>  $eligible  ordered by score descending
     */
    private function roulette(Collection $eligible): ?TrackRequest
    {
        $positive = $eligible->filter(fn (TrackRequest $request): bool => (int) $request->score > 0)->values();

        if ($positive->isEmpty()) {
            return $eligible->first();
        }

        $total = (int) $positive->sum(fn (TrackRequest $request): int => (int) $request->score);
        $ticket = $this->randomizer->between(1, $total);

        foreach ($positive as $request) {
            $ticket -= (int) $request->score;

            if ($ticket <= 0) {
                return $request;
            }
        }

        return $positive->last();
    }
}
