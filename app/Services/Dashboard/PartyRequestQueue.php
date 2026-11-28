<?php

namespace App\Services\Dashboard;

use App\Http\Resources\V1\UpcomingSongResource;
use App\Models\Party;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Services\Queue\QueueListing;
use App\Services\UpcomingSongAugmentService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class PartyRequestQueue
{
    private const int UP_NEXT_SIZE = 3;

    private const int UPCOMING_SIZE = 10;

    public function __construct(private readonly UpcomingSongAugmentService $augment, private readonly QueueListing $listing) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function upNext(Party $party, User $viewer, UpcomingSong|false|null $sent = false): array
    {
        $sent = $sent === false ? $this->sentRequest($party) : $sent;
        $page = $this->listing->page($party, 0, self::UP_NEXT_SIZE - ($sent === null ? 0 : 1));

        return array_merge(
            $sent === null ? [] : $this->requests(new Collection([$sent]), $viewer, [], sent: true),
            $this->requests($page['items'], $viewer, $page['states'], sent: false),
        );
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function upcoming(Party $party, User $viewer, UpcomingSong|false|null $sent = false): array
    {
        $sent = $sent === false ? $this->sentRequest($party) : $sent;
        $page = $this->listing->page($party, 0, self::UPCOMING_SIZE - ($sent === null ? 0 : 1));

        return [
            'items' => array_merge(
                $sent === null ? [] : $this->requests(new Collection([$sent]), $viewer, [], sent: true),
                $this->requests($page['items'], $viewer, $page['states'], sent: false),
            ),
            'total' => $page['total'] + ($sent === null ? 0 : 1),
        ];
    }

    public function sentRequest(Party $party): ?UpcomingSong
    {
        $sent = $party->upcoming()
            ->with(['user', 'song', 'song.album', 'song.artists'])
            ->whereNotNull('queued_at')
            ->doesntHave('played')
            ->orderByDesc('queued_at')
            ->first();

        return $sent?->setRelation('party', $party);
    }

    /**
     * @param  Collection<int, UpcomingSong>|SupportCollection<int, UpcomingSong>  $requests
     * @param  array<int, array{held: bool, next: bool}>  $states
     * @return list<array<string, mixed>>
     */
    private function requests(Collection|SupportCollection $requests, User $viewer, array $states, bool $sent): array
    {
        $augmented = $this->augment->augmentCollection($requests, $viewer);

        return array_values($requests->map(function (UpcomingSong $request) use ($augmented, $states, $sent): array {
            $resource = new UpcomingSongResource($request);
            $resource->augment($augmented[$request->id] ?? null);
            $resource->queueState($states[$request->id] ?? null);

            return $resource->toArray(request()) + ['sent' => $sent];
        })->all());
    }
}
