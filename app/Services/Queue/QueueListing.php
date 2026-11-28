<?php

namespace App\Services\Queue;

use App\Models\Party;
use App\Models\UpcomingSong;
use App\Mods\ModExtensions;
use App\Services\QueueSelector;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Pages through the Party's waiting Requests in the order the selector ranks them, with the Request that plays next flagged
 * and held Requests (not yet released) listed after it and flagged as held.
 */
class QueueListing
{
    public function __construct(private readonly QueueSelector $selector, private readonly ModExtensions $mods) {}

    /**
     * @return array{items: Collection<int, UpcomingSong>, total: int, states: array<int, array{held: bool, next: bool}>}
     */
    public function page(Party $party, int $offset, int $limit): array
    {
        $queue = $this->selector->rankedQueue($party, includeHeld: true);
        $ordered = $queue['ready']->concat($queue['held'])->values();
        $nextId = $queue['ready']->first()?->id;
        $heldIds = $queue['held']->pluck('id')->flip();

        $items = new EloquentCollection($ordered->slice($offset, $limit)->values()->all());
        $items->loadMissing(['user', 'song.album', 'song.artists']);
        $this->mods->preloadDecorations($party, $items);

        $states = [];
        foreach ($items as $request) {
            $states[$request->id] = ['held' => $heldIds->has($request->id), 'next' => $request->id === $nextId];
        }

        return ['items' => $items, 'total' => $ordered->count(), 'states' => $states];
    }
}
