<?php

namespace App\Domain\Queue\Broadcast;

use App\Domain\Mod\Actions\ResolveDecorations;
use App\Domain\Queue\RequestStatus;
use App\Models\Party;
use App\Models\TrackRequest;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class PartyQueueSnapshot
{
    public const VERSION = 1;

    public function __construct(private readonly ResolveDecorations $decorations) {}

    /**
     * @return array{version: int, sequence: int, code: string, now_playing: array<string, mixed>|null, up_next: array<string, mixed>|null, queue: list<array<string, mixed>>}
     */
    public function build(Party $party): array
    {
        $requests = TrackRequest::query()
            ->where('party_id', $party->id)
            ->whereIn('status', [RequestStatus::Playing, RequestStatus::UpNext, RequestStatus::Queued])
            ->with('requester.user')
            ->withSum('votes as score', 'value')
            ->withCount([
                'ratings as likes' => fn ($query) => $query->where('value', '>', 0),
                'ratings as dislikes' => fn ($query) => $query->where('value', '<', 0),
            ])
            ->orderByRaw('COALESCE(score, 0) desc')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $nowPlaying = $requests->first(fn (TrackRequest $request): bool => $request->status === RequestStatus::Playing);
        $upNext = $requests->first(fn (TrackRequest $request): bool => $request->status === RequestStatus::UpNext);

        return [
            'version' => self::VERSION,
            'sequence' => $this->nextSequence($party),
            'code' => $party->code,
            'now_playing' => $nowPlaying === null ? null : $this->entry($nowPlaying),
            'up_next' => $upNext === null ? null : $this->entry($upNext),
            'queue' => array_values($requests
                ->filter(fn (TrackRequest $request): bool => $request->status === RequestStatus::Queued)
                ->map(fn (TrackRequest $request): array => $this->entry($request))
                ->all()),
        ];
    }

    private function nextSequence(Party $party): int
    {
        $key = "party-queue-sequence:{$party->id}";
        Cache::add($key, 0, now()->addDay());

        return (int) Cache::increment($key);
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(TrackRequest $request): array
    {
        $requester = $request->requester?->user;

        return [
            'id' => $request->id,
            'track' => [
                'title' => $request->title,
                'artists' => $request->artists,
                'album' => $request->album,
                'artwork_url' => $request->artwork_url,
                'duration_ms' => $request->duration_ms,
                'explicit' => $request->explicit,
            ],
            'status' => $request->status->value,
            'score' => (int) $request->score,
            'likes' => (int) $request->likes,
            'dislikes' => (int) $request->dislikes,
            'requested_by' => ['name' => $requester instanceof User ? $requester->nickname : null],
            'decorations' => ($this->decorations)($request),
        ];
    }
}
