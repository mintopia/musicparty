<?php

namespace App\Domain\Queue\Actions;

use App\Domain\Queue\Data\QueueAdvance;
use App\Domain\Queue\Events\TrackEnded;
use App\Domain\Queue\Events\TrackStarted;
use App\Domain\Queue\RequestStatus;
use App\Models\Party;
use App\Models\Play;
use App\Models\TrackRequest;
use Illuminate\Support\Facades\DB;

readonly class AdvanceQueue
{
    /**
     * Applies a Player track change, or with a null track the Player stopping: Playing becomes Played, Up Next becomes Playing and is recorded as a Play.
     */
    public function __invoke(Party $party, ?string $providerTrackId): QueueAdvance
    {
        return DB::transaction(function () use ($party, $providerTrackId): QueueAdvance {
            Party::query()->whereKey($party->id)->lockForUpdate()->firstOrFail();

            $requests = TrackRequest::query()
                ->where('party_id', $party->id)
                ->whereIn('status', [RequestStatus::Playing, RequestStatus::UpNext])
                ->get();

            $playing = $requests->where('status', RequestStatus::Playing);
            $upNext = $requests->firstWhere('status', RequestStatus::UpNext);
            $expected = $providerTrackId !== null && $upNext !== null && $upNext->provider_track_id === $providerTrackId;

            if (! $expected && $playing->contains('provider_track_id', $providerTrackId)) {
                return new QueueAdvance($playing->firstWhere('provider_track_id', $providerTrackId), duplicate: true);
            }

            foreach ($playing as $previous) {
                $this->finish($previous);
                DB::afterCommit(fn () => TrackEnded::dispatch($party, $previous));
            }

            if (! $expected) {
                return new QueueAdvance(null, unexpectedTrack: $providerTrackId !== null);
            }

            $upNext->forceFill(['status' => RequestStatus::Playing, 'started_at' => now()])->save();
            $this->recordPlay($upNext);
            DB::afterCommit(fn () => TrackStarted::dispatch($party, $upNext));

            return new QueueAdvance($upNext);
        });
    }

    private function finish(TrackRequest $request): void
    {
        $request->forceFill(['status' => RequestStatus::Played])->save();
    }

    private function recordPlay(TrackRequest $request): void
    {
        Play::query()->firstOrCreate(
            ['track_request_id' => $request->id],
            [
                'party_id' => $request->party_id,
                'party_member_id' => $request->party_member_id,
                'provider_track_id' => $request->provider_track_id,
                'title' => $request->title,
                'artists' => $request->artists,
                'album' => $request->album,
                'artwork_url' => $request->artwork_url,
                'duration_ms' => $request->duration_ms,
                'explicit' => $request->explicit,
                'selection_mode' => $request->selection_mode,
                'selection_score' => $request->selection_score,
                'played_at' => $request->started_at,
            ],
        );
    }
}
