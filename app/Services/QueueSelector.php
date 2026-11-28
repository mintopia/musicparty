<?php

namespace App\Services;

use App\Enums\Purpose;
use App\Models\Party;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Mods\Contracts\ModifiesScore;
use App\Mods\ModExtensions;
use App\Services\Music\MusicProviderFactory;
use App\Services\Requests\RequestLocks;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Random\Randomizer;
use Throwable;

class QueueSelector
{
    public const QUEUE_LENGTH = 1;

    /** @var array<int, list<ModifiesScore>> */
    protected array $modifiers = [];

    protected bool $selecting = false;

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
        $this->modifiers = [];
        $this->selecting = true;
        try {
            $this->sendNextRequests($party);
        } finally {
            $this->selecting = false;
            $this->modifiers = [];
        }
    }

    protected function sendNextRequests(Party $party): void
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
        $selected = $this->pick($party, $toAdd);

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
     * The Requests that would be sent next: Scheduled first, then ranked or weighted.
     *
     * @return Collection<int, UpcomingSong>
     */
    public function pick(Party $party, int $limit): Collection
    {
        return $this->whileSelecting(function () use ($party, $limit): Collection {
            $current = $party->fresh() ?? $party;
            $queue = $this->rankedQueue($party);
            $candidates = $queue['ready'];
            $due = $candidates->filter(fn (UpcomingSong $request): bool => $request->scheduled_for !== null)->take($limit)->values();
            $rest = $candidates->reject(fn (UpcomingSong $request): bool => $request->scheduled_for !== null)->values();
            $remaining = $limit - $due->count();

            return $due->concat($remaining < 1 ? [] : ($current->weighted
                ? $this->selectWeighted($rest, $remaining, $queue['scores'])
                : $this->selectRanked($rest, $remaining)))->values();
        });
    }

    /**
     * The Party's waiting Requests in the order the selector ranks them, computed once with a single Effective Score per Request.
     * Ready Requests are selectable; held ones (only returned when $includeHeld) are released later.
     *
     * @return array{ready: Collection<int, UpcomingSong>, held: Collection<int, UpcomingSong>, scores: array<int, int>}
     */
    public function rankedQueue(Party $party, bool $includeHeld = false): array
    {
        return $this->whileSelecting(function () use ($party, $includeHeld): array {
            $now = Carbon::now();

            $waiting = $party->upcoming()
                ->whereNull('queued_at')
                ->whereNull('flagged_at')
                ->unless($includeHeld, fn ($query) => $query->where(function ($query) use ($now): void {
                    $query->whereNull('not_before')->orWhere('not_before', '<=', $now);
                }))
                ->with('song')
                ->get()
                ->each(fn (UpcomingSong $request): UpcomingSong => $request->setRelation('party', $party));
            $scores = $this->scoreMap($waiting);

            /** @var array{0: Collection<int, UpcomingSong>, 1: Collection<int, UpcomingSong>} $parts */
            $parts = $waiting->partition(fn (UpcomingSong $request): bool => $request->not_before === null || $request->not_before <= $now)->all();

            return [
                'ready' => $this->sortRanked($parts[0], $scores),
                'held' => $this->sortRanked($parts[1], $scores),
                'scores' => $scores,
            ];
        });
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function whileSelecting(callable $callback): mixed
    {
        $wasSelecting = $this->selecting;
        $this->selecting = true;
        try {
            return $callback();
        } finally {
            if (! $wasSelecting) {
                $this->selecting = false;
                $this->modifiers = [];
            }
        }
    }

    /**
     * @param  Collection<int, UpcomingSong>  $requests
     * @return array<int, int>
     */
    public function scoreMap(Collection $requests): array
    {
        $scores = [];
        foreach ($requests as $request) {
            $scores[$request->id] = $this->effectiveScore($request);
        }

        return $scores;
    }

    /**
     * @param  Collection<int, UpcomingSong>  $requests
     * @param  array<int, int>  $scores
     * @return Collection<int, UpcomingSong>
     */
    protected function sortRanked(Collection $requests, array $scores): Collection
    {
        return $requests
            ->sort(fn (UpcomingSong $a, UpcomingSong $b): int => [$a->scheduled_for === null ? 1 : 0, $a->scheduled_for, $scores[$b->id], $a->created_at, $a->id] <=> [$b->scheduled_for === null ? 1 : 0, $b->scheduled_for, $scores[$a->id], $b->created_at, $b->id])
            ->values();
    }

    /**
     * The single step that turns a Request into the score used for selection: the stored score passed through
     * every ModifiesScore mod enabled for the Request's Party. Modifiers are resolved once per Party while a selection runs.
     */
    public function effectiveScore(UpcomingSong $request): int
    {
        $score = $request->score;
        foreach ($this->scoreModifiers($request->party) as $modifier) {
            try {
                $score = $modifier->modifyScore($request->party, $request, $score);
            } catch (Throwable $exception) {
                Log::warning("{$request->party}: Mod ".$modifier::class." failed to modify a score: {$exception->getMessage()}");
            }
        }

        return $score;
    }

    /**
     * @return list<ModifiesScore>
     */
    protected function scoreModifiers(Party $party): array
    {
        if (! $this->selecting) {
            return app(ModExtensions::class)->for($party, ModifiesScore::class);
        }

        return $this->modifiers[$party->id] ??= app(ModExtensions::class)->for($party, ModifiesScore::class);
    }

    /**
     * Adds a Request to the Party's Queue; the entry point for core features and mods. Reuses a waiting Request for the Song.
     *
     * @param  array<string, mixed>  $attributes  Request attributes such as not_before, score_adjustment. A null $user makes a system Request.
     */
    public function addRequest(Party $party, Song $song, ?User $user = null, array $attributes = []): UpcomingSong
    {
        return $this->withRequestLock($party, fn (): UpcomingSong => $this->addRequestLocked($party, $song, $user, $attributes));
    }

    /**
     * Runs $callback holding the Party's request lock, which serialises every path that creates Requests. Re-entrant within one call stack, as cache locks are not.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function withRequestLock(Party $party, Closure $callback, ?int $waitSeconds = null): mixed
    {
        return app(RequestLocks::class)->run($party, $callback, $waitSeconds);
    }

    /**
     * addRequest for callers already inside withRequestLock.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function addRequestLocked(Party $party, Song $song, ?User $user = null, array $attributes = []): UpcomingSong
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
     * @param  array<int, int>  $scores
     * @return Collection<int, UpcomingSong>
     */
    protected function selectWeighted(Collection $candidates, int $limit, array $scores): Collection
    {
        $randomizer = app(Randomizer::class);
        $pool = collect($randomizer->shuffleArray(
            $candidates->filter(fn (UpcomingSong $request): bool => $scores[$request->id] > 0)->sortBy('id')->values()->all()
        ));
        $selected = new Collection;

        for ($i = 0; $i < $limit; $i++) {
            $sum = $pool->sum(fn (UpcomingSong $request): int => $scores[$request->id]);
            $random = $randomizer->getInt(0, (int) $sum);
            foreach ($pool as $index => $request) {
                $random -= $scores[$request->id];
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
