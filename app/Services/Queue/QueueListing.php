<?php

namespace App\Services\Queue;

use App\Models\Party;
use App\Models\UpcomingSong;
use App\Mods\ModExtensions;
use App\Services\QueueSelector;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Pages through the Party's waiting Requests in the order the selector ranks them, with the Request that plays next flagged
 * and held Requests (not yet released) listed after it and flagged as held.
 */
class QueueListing
{
    private const int CACHE_SECONDS = 5;

    public function __construct(private readonly QueueSelector $selector, private readonly ModExtensions $mods) {}

    public function position(Party $party, UpcomingSong $request): ?int
    {
        $queue = $this->selector->rankedQueue($party, includeHeld: true);
        $index = $queue['ready']->concat($queue['held'])->values()->search(fn (UpcomingSong $candidate): bool => $candidate->id === $request->id);

        return $index === false ? null : $index + 1;
    }

    public static function forget(int $partyId): void
    {
        Cache::forget(self::cacheKey($partyId));
    }

    /**
     * @return array{items: Collection<int, UpcomingSong>, total: int, random_order: bool, states: array<int, array{held: bool, next?: bool, random_order?: bool}>}
     */
    public function page(Party $party, int $offset, int $limit): array
    {
        $ranked = $this->ranked($party);
        $ordered = array_merge($ranked['ready'], $ranked['held']);
        $slice = array_slice($ordered, $offset, $limit);

        $loaded = UpcomingSong::query()
            ->whereIn('id', $slice)
            ->with(['user', 'song.album', 'song.artists'])
            ->get()
            ->each(fn (UpcomingSong $request): UpcomingSong => $request->setRelation('party', $party))
            ->keyBy('id');
        $items = new EloquentCollection(array_values(array_filter(array_map(fn (int $id): ?UpcomingSong => $loaded->get($id), $slice))));
        $this->mods->preloadDecorations($party, $items);

        $randomOrder = (bool) $party->weighted;
        $nextId = $ranked['ready'][0] ?? null;
        $heldIds = array_flip($ranked['held']);

        $states = [];
        foreach ($items as $request) {
            $states[$request->id] = ['held' => isset($heldIds[$request->id])]
                + ($randomOrder ? ['random_order' => true] : ['next' => $request->id === $nextId]);
        }

        return ['items' => $items, 'total' => count($ordered), 'random_order' => $randomOrder, 'states' => $states];
    }

    /**
     * @return array{ready: list<int>, held: list<int>}
     */
    private function ranked(Party $party): array
    {
        return Cache::remember(self::cacheKey($party->id), self::CACHE_SECONDS, function () use ($party): array {
            $queue = $this->selector->rankedQueue($party, includeHeld: true);

            return [
                'ready' => array_values($queue['ready']->pluck('id')->map(fn ($id): int => (int) $id)->all()),
                'held' => array_values($queue['held']->pluck('id')->map(fn ($id): int => (int) $id)->all()),
            ];
        });
    }

    private static function cacheKey(int $partyId): string
    {
        return "queue-listing:{$partyId}";
    }
}
