<?php

namespace App\Domain\Queue\Actions;

use App\Domain\Party\Actions\RecordPartyLogEntry;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Jobs\BroadcastPartyQueue;
use App\Domain\Queue\Models\TrackRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

readonly class SetRequestNotBefore
{
    public function __construct(private RecordPartyLogEntry $record) {}

    /**
     * Holds a Request out of selection until the given time, or releases it with null. Returns whether anything changed.
     */
    public function __invoke(Party $party, TrackRequest $request, ?CarbonImmutable $notBefore, string $systemActor): bool
    {
        $changed = DB::transaction(function () use ($party, $request, $notBefore, $systemActor): bool {
            $locked = TrackRequest::query()->where('party_id', $party->id)->whereKey($request->id)->lockForUpdate()->first();

            if ($locked === null || $locked->not_before?->getTimestamp() === $notBefore?->getTimestamp()) {
                return false;
            }

            $previous = $locked->not_before;
            $locked->forceFill(['not_before' => $notBefore])->save();

            ($this->record)($party, 'request.not_before_set', subject: $locked->title, details: [
                'request_id' => $locked->id,
                'not_before' => $notBefore?->toIso8601String(),
                'previous_not_before' => $previous?->toIso8601String(),
            ], systemActor: $systemActor);

            return true;
        });

        if ($changed) {
            BroadcastPartyQueue::dispatch($party->code);
        }

        return $changed;
    }
}
