<?php

namespace App\Services;

use App\Enums\Purpose;
use App\Models\Party;
use App\Models\UpcomingSong;
use App\Services\Music\MusicProviderFactory;
use App\Services\Spotify\SpotifyGateway;
use App\Support\SpotifyPayload;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Random\Randomizer;

class QueueSelector
{
    public const QUEUE_LENGTH = 1;

    public function __construct(
        protected MusicProviderFactory $providers,
        protected SpotifyGateway $gateway,
        protected FallbackPlaylist $fallback,
    ) {}

    /**
     * Sends the next Requests to the Player and tops the Party up from its Fallback Playlist.
     */
    public function run(Party $party): void
    {
        $this->sendNext($party);
        $this->fallback->backfill($party);
    }

    public function sendNext(Party $party): void
    {
        Log::debug("{$party}: Checking if we need to add tracks to queue");
        $current = $party->fresh() ?? $party;
        $queue = SpotifyPayload::object($this->gateway->requireClient($party->user)->getMyQueue());
        $queueItems = array_map(SpotifyPayload::object(...), (array) $queue->queue);
        $count = count($queueItems);
        $ids = collect($queueItems)->pluck('id')->unique();
        /* The Spotify queue endpoint returns 10-20 items, and when nothing is queued it pads the result with copies
         * of the current context. So the number of unique items minus one (the playing track) is the real depth.
         */
        Log::debug("{$party}: Queue contains {$count} items, unique: {$ids->count()}");
        $toAdd = self::QUEUE_LENGTH - ($ids->count() - 1);
        if ($toAdd < 1) {
            Log::debug("{$party}: No tracks to add");

            return;
        }

        Log::debug("{$party}: Adding {$toAdd} tracks to the queue");
        $candidates = $this->candidates($party);
        $selected = $current->weighted
            ? $this->selectWeighted($candidates, $toAdd)
            : $this->selectRanked($candidates, $toAdd);

        if ($selected->isEmpty()) {
            Log::debug("{$party}: Found no songs in the upcoming songs list to backfill");

            return;
        }

        $provider = $this->providers->forParty($party, Purpose::Control);
        foreach ($selected as $request) {
            if ($request->fresh()?->queued_at !== null) {
                Log::debug("{$party}: {$request->song} was already sent to the player");

                continue;
            }
            Log::info("{$party}: Adding {$request->song} to queue");
            $track = $request->song->toTrack();
            $provider->sendToPlayer($track);
            $request->queued_at = Carbon::now();
            $request->sent_track_id = $track->id;
            $request->save();
            if ($current->history_playlist_id !== null) {
                Log::info("{$party}: Adding {$request->song} to history playlist");
                $provider->appendToPlaylist($current->history_playlist_id, [$track]);
            }
        }
    }

    /**
     * The single step that turns a Request into the score used for selection. Mods (E8) override this.
     */
    public function effectiveScore(UpcomingSong $request): int
    {
        return $request->score;
    }

    /**
     * Waiting, released Requests ranked by Effective Score, then age.
     *
     * @return Collection<int, UpcomingSong>
     */
    protected function candidates(Party $party): Collection
    {
        $now = Carbon::now();

        return $party->upcoming()
            ->whereNull('queued_at')
            ->where(function ($query) use ($now): void {
                $query->whereNull('not_before')->orWhere('not_before', '<=', $now);
            })
            ->with('song')
            ->get()
            ->map(fn (UpcomingSong $request): array => [$request, $this->effectiveScore($request)])
            ->sort(fn (array $a, array $b): int => [$b[1], $a[0]->created_at, $a[0]->id] <=> [$a[1], $b[0]->created_at, $b[0]->id])
            ->map(fn (array $pair): UpcomingSong => $pair[0])
            ->values();
    }

    /**
     * @param  Collection<int, UpcomingSong>  $candidates
     * @return Collection<int, UpcomingSong>
     */
    protected function selectRanked(Collection $candidates, int $limit): Collection
    {
        return $candidates->take($limit)->values();
    }

    /**
     * Draws by cumulative Effective Score, then fills any shortfall in ranked order.
     *
     * @param  Collection<int, UpcomingSong>  $candidates
     * @return Collection<int, UpcomingSong>
     */
    protected function selectWeighted(Collection $candidates, int $limit): Collection
    {
        $randomizer = app(Randomizer::class);
        $pool = collect($randomizer->shuffleArray(
            $candidates->filter(fn (UpcomingSong $request): bool => $this->effectiveScore($request) > 0)->sortBy('id')->values()->all()
        ));
        $selected = new Collection;

        for ($i = 0; $i < $limit; $i++) {
            $sum = $pool->sum(fn (UpcomingSong $request): int => $this->effectiveScore($request));
            $random = $randomizer->getInt(0, (int) $sum);
            foreach ($pool as $index => $request) {
                $random -= $this->effectiveScore($request);
                if ($random <= 0) {
                    $pool->forget($index);
                    $selected->push($request);
                    break;
                }
            }
        }

        $remaining = $limit - $selected->count();
        if ($remaining > 0) {
            $chosen = $selected->pluck('id');
            $selected = $selected->concat(
                $candidates->reject(fn (UpcomingSong $request): bool => $chosen->contains($request->id))->take($remaining)
            );
        }

        return $selected->values();
    }
}
