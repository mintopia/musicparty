<?php

namespace App\Services\Dashboard;

use App\Http\Resources\V1\UpcomingSongResource;
use App\Models\Party;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Services\UpcomingSongAugmentService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartyRequestQueue
{
    private const int UP_NEXT_SIZE = 3;

    private const int UPCOMING_SIZE = 10;

    public function __construct(private readonly UpcomingSongAugmentService $augment) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function upNext(Party $party, User $viewer): array
    {
        return $this->requests($this->upNextRequests($party), $viewer);
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function upcoming(Party $party, User $viewer): array
    {
        $sent = $this->sentRequest($party);
        $unsent = $this->unsent($party)->take(self::UPCOMING_SIZE - ($sent === null ? 0 : 1))->get();

        return [
            'items' => array_merge(
                $sent === null ? [] : $this->requests(new Collection([$sent]), $viewer, sent: true),
                $this->requests($unsent, $viewer, sent: false),
            ),
            'total' => $this->unsent($party)->count() + ($sent === null ? 0 : 1),
        ];
    }

    /**
     * @return HasMany<UpcomingSong, Party>
     */
    private function unsent(Party $party): HasMany
    {
        return $party->upcoming()
            ->with(['user', 'song', 'song.album', 'song.artists'])
            ->whereNull('queued_at')
            ->whereNull('flagged_at')
            ->orderByRaw('scheduled_for is null')
            ->orderBy('scheduled_for')
            ->orderBy('score', 'desc')
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc');
    }

    private function sentRequest(Party $party): ?UpcomingSong
    {
        return $party->upcoming()
            ->with(['user', 'song', 'song.album', 'song.artists'])
            ->whereNotNull('queued_at')
            ->doesntHave('played')
            ->orderByDesc('queued_at')
            ->first();
    }

    /**
     * @return Collection<int, UpcomingSong>
     */
    private function upNextRequests(Party $party): Collection
    {
        $sent = $this->sentRequest($party);
        $items = $this->unsent($party)->take(self::UP_NEXT_SIZE - ($sent === null ? 0 : 1))->get();

        return new Collection($sent === null ? $items->all() : [$sent, ...$items->all()]);
    }

    /**
     * @param  Collection<int, UpcomingSong>  $requests
     * @return list<array<string, mixed>>
     */
    private function requests(Collection $requests, User $viewer, ?bool $sent = null): array
    {
        $augmented = $this->augment->augmentCollection($requests, $viewer);

        return array_values($requests->map(function (UpcomingSong $request) use ($augmented, $sent): array {
            $resource = new UpcomingSongResource($request);
            $resource->augment($augmented[$request->id] ?? null);

            return $resource->toArray(request()) + ['sent' => $sent ?? $request->queued_at !== null];
        })->all());
    }
}
