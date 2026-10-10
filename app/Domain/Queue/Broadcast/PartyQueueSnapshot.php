<?php

namespace App\Domain\Queue\Broadcast;

use App\Domain\Party\Models\Party;
use App\Domain\Queue\Actions\RankQueue;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\Presenters\QueueEntryPresenter;
use App\Domain\Queue\RequestStatus;

class PartyQueueSnapshot
{
    public const VERSION = 1;

    public function __construct(private readonly RankQueue $rank, private readonly QueueEntryPresenter $presenter) {}

    /**
     * @return array{version: int, code: string, now_playing: array<string, mixed>|null, up_next: array<string, mixed>|null, queue: list<array<string, mixed>>}
     */
    public function build(Party $party): array
    {
        $requests = ($this->rank)($party, TrackRequest::query()
            ->where('party_id', $party->id)
            ->whereIn('status', [RequestStatus::Playing, RequestStatus::UpNext, RequestStatus::Queued])
            ->with('requester.user', 'party')
            ->with(['play' => fn ($query) => $query->withRatingSummary()]))->requests;

        $nowPlaying = $requests->first(fn (TrackRequest $request): bool => $request->status === RequestStatus::Playing);
        $upNext = $requests->first(fn (TrackRequest $request): bool => $request->status === RequestStatus::UpNext);

        return [
            'version' => self::VERSION,
            'code' => $party->code,
            'now_playing' => $nowPlaying === null ? null : $this->entry($nowPlaying),
            'up_next' => $upNext === null ? null : $this->entry($upNext),
            'queue' => array_values($requests
                ->filter(fn (TrackRequest $request): bool => $request->status === RequestStatus::Queued)
                ->map(fn (TrackRequest $request): array => $this->entry($request))
                ->all()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(TrackRequest $request): array
    {
        return ($this->presenter)($request);
    }
}
