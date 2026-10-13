<?php

namespace App\Services;

use App\Enums\Purpose;
use App\Models\Party;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Services\Music\MusicProviderFactory;
use App\Services\Queue\CandidateFilter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Random\Randomizer;

class QueueSelector
{
    public const QUEUE_LENGTH = 1;

    public function __construct(
        protected MusicProviderFactory $providers,
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
        $provider = $this->providers->forParty($party, Purpose::Control);
        $depth = $provider->queueDepth();
        Log::debug("{$party}: Queue contains {$depth} tracks after the current one");
        $toAdd = self::QUEUE_LENGTH - $depth;
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

        foreach ($selected as $request) {
            if ($request->fresh()?->queued_at !== null) {
                Log::debug("{$party}: {$request->song} was already sent to the player");

                continue;
            }
            Log::info("{$party}: Adding {$request->song} to queue");
            $track = $request->song->toTrack();
            $provider->sendToPlayer($track);
            $request->queued_at = Carbon::now();
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
     * Adds a Request to the Party's Queue; the entry point for core features and mods. Reuses a waiting Request for the Song.
     *
     * @param  array<string, mixed>  $attributes  Request attributes such as not_before, score_adjustment, css_classes.
     */
    public function addRequest(Party $party, Song $song, ?User $user = null, array $attributes = []): UpcomingSong
    {
        $request = $party->upcoming()->whereNull('queued_at')->where('song_id', $song->id)->first() ?? new UpcomingSong;
        if (! $request->exists) {
            $request->party()->associate($party);
            $request->song()->associate($song);
            $request->user()->associate($user);
        }
        $request->forceFill($attributes)->save();
        $request->updateScore();

        return $request;
    }

    /**
     * Waiting, unflagged, released Requests that pass every tagged candidate filter, ranked by Effective Score, then age.
     *
     * @return Collection<int, UpcomingSong>
     */
    protected function candidates(Party $party): Collection
    {
        /** @var list<CandidateFilter> $filters */
        $filters = iterator_to_array(app()->tagged('queue.candidate-filters'), false);

        $now = Carbon::now();

        return $party->upcoming()
            ->whereNull('queued_at')
            ->whereNull('flagged_at')
            ->where(function ($query) use ($now): void {
                $query->whereNull('not_before')->orWhere('not_before', '<=', $now);
            })
            ->with('song')
            ->get()
            ->filter(fn (UpcomingSong $request): bool => collect($filters)->every(fn (CandidateFilter $filter): bool => $filter->allows($request)))
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
