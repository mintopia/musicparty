<?php

namespace App\Domain\Queue\Actions;

use App\Domain\Party\Actions\RecordPartyLogEntry;
use App\Domain\Party\PartyState;
use App\Domain\Queue\RequestStatus;
use App\Models\Party;
use App\Models\TrackRequest;
use Illuminate\Support\Facades\DB;

readonly class SelectUpNext
{
    public const string MODE = 'deterministic';

    public function __construct(private RecordPartyLogEntry $record) {}

    /**
     * Returns the newly locked Up Next Request, or null when one already exists or none is eligible.
     */
    public function __invoke(Party $party): ?TrackRequest
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

            $candidate = TrackRequest::query()
                ->where('party_id', $locked->id)
                ->where('status', RequestStatus::Queued)
                ->where(fn ($query) => $query->whereNull('not_before')->orWhere('not_before', '<=', now()))
                ->withSum('votes as score', 'value')
                ->orderByRaw('COALESCE(score, 0) desc')
                ->orderBy('created_at')
                ->orderBy('id')
                ->first();

            if ($candidate === null) {
                return null;
            }

            $score = (int) $candidate->score;

            $candidate->forceFill([
                'status' => RequestStatus::UpNext,
                'up_next_at' => now(),
                'enqueued_at' => null,
                'selection_mode' => self::MODE,
                'selection_score' => $score,
            ])->save();

            ($this->record)($locked, 'queue.selected', subject: $candidate->title, details: [
                'request_id' => $candidate->id,
                'mode' => self::MODE,
                'score' => $score,
                'fallback' => $candidate->party_member_id === null,
            ], systemActor: 'queue');

            return $candidate;
        });
    }
}
