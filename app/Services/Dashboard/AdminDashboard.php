<?php

namespace App\Services\Dashboard;

use App\Enums\AiReviewOutcome;
use App\Models\AiReview;
use App\Models\LinkedAccount;
use App\Models\Party;
use App\Models\PlayedSong;
use App\Models\SocialProvider;
use App\Models\User;
use App\Services\Music\Data\PlaybackSource;
use App\Services\Music\MusicProviderFactory;
use App\Services\Playback\EncoreConnection;
use App\Services\PlaybackStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds the props for the Admin dashboard page; shapes mirror AdminDashboardProps in resources/js/types.ts.
 *
 * @phpstan-type Action array{label: string, url: string}
 * @phpstan-type Attention array{key: string, severity: 'error'|'warn', title: string, detail: string, action: Action}
 * @phpstan-type LiveParty array{code: string, name: string, url: string, owner: string, state: string, stateLabel: string|null, stateDetail: string|null, track: array{name: string, artists: string, image: string|null, lastKnown: bool}|null, guestsOnline: int|null, requestsWaiting: int, flagged: int}
 */
class AdminDashboard
{
    private const int LIVE_REPORT_SECONDS = 1800;

    private const int STALE_SECONDS = 60;

    private const int RECENT_SIZE = 4;

    public function __construct(
        protected SystemHealth $health,
        protected ReverbStatus $reverb,
        protected EncoreConnection $encore,
        protected PlaybackStatus $status,
        protected MusicProviderFactory $providers,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function props(): array
    {
        $parties = $this->liveParties();

        return [
            'generatedAt' => now()->toIso8601String(),
            'liveParties' => $parties->map(fn (array $row): array => $row['party'])->values()->all(),
            'quietFor' => $parties->isEmpty() ? $this->quietFor() : null,
            'attention' => $this->attention(array_values($parties->pluck('model')->all())),
            'health' => $this->health->checks(),
            'activity' => $this->activity(),
            'links' => [
                'pulse' => route('pulse'),
                'horizon' => route('horizon.index'),
                'createParty' => route('parties.create'),
            ],
        ];
    }

    /**
     * @return Collection<int, array{model: Party, party: LiveParty}>
     */
    protected function liveParties(): Collection
    {
        $rows = Party::query()
            ->where('active', true)
            ->with(['user', 'song.album', 'song.artists'])
            ->withCount([
                'upcoming as waiting_count' => fn ($query) => $query->whereNull('queued_at')->whereNull('flagged_at'),
                'upcoming as flagged_count' => fn ($query) => $query->whereNull('queued_at')->whereNotNull('flagged_at'),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Party $party): array => ['model' => $party, 'guests' => $this->reverb->subscribers("party.{$party->code}")])
            ->filter(fn (array $row): bool => $this->isLive($row['model'], $row['guests']));

        return $rows->map(fn (array $row): array => ['model' => $row['model'], 'party' => $this->liveParty($row['model'], $row['guests'])])->values();
    }

    protected function isLive(Party $party, ?int $guests): bool
    {
        return ($guests ?? 0) > 0
            || ($party->playback_received_at !== null && $party->playback_received_at->greaterThan(now()->subSeconds(self::LIVE_REPORT_SECONDS)));
    }

    /**
     * @return LiveParty
     */
    protected function liveParty(Party $party, ?int $guests): array
    {
        [$state, $label, $detail] = $this->playback($party);
        $song = $party->song;

        return [
            'code' => $party->code,
            'name' => $party->name,
            'url' => route('parties.dashboard', $party->code),
            'owner' => $party->user->nickname,
            'state' => $state,
            'stateLabel' => $label,
            'stateDetail' => $detail,
            'track' => $song === null ? null : [
                'name' => $song->name,
                'artists' => $song->artists->pluck('name')->implode(', '),
                'image' => $song->album->image_url,
                'lastKnown' => $state === 'stale',
            ],
            'guestsOnline' => $guests,
            'requestsWaiting' => (int) $party->getAttribute('waiting_count'),
            'flagged' => (int) $party->getAttribute('flagged_count'),
        ];
    }

    /**
     * Mirrors derivePlaybackStatus in resources/js/lib/playbackStatus.ts without asking Spotify.
     *
     * @return array{0: string, 1: string|null, 2: string|null}
     */
    protected function playback(Party $party): array
    {
        if ($this->providers->partyNeedsRelink($party)) {
            return ['error', 'Re-link needed', "{$party->user->nickname}'s Spotify link has expired."];
        }
        if ($this->isStale($party)) {
            return ['stale', null, 'No report for '.$this->words($party->playback_received_at)];
        }
        $reported = $party->playback_received_at === null ? null : 'Last report '.$party->playback_received_at->diffForHumans();
        if ($party->song_id !== null && $this->status->recall($party)?->isPlaying === true) {
            return ['playing', null, null];
        }
        if ($party->song_id !== null) {
            return ['paused', null, $reported];
        }

        return ['idle', null, $party->getAttribute('waiting_count') > 0 ? null : 'Nothing queued'];
    }

    protected function isStale(Party $party): bool
    {
        $received = $party->playback_received_at;
        if ($received === null || $received->greaterThan(now()->subSeconds(self::STALE_SECONDS))) {
            return false;
        }

        return $party->playback_source !== PlaybackSource::Encore || $this->encore->isConnected($party) !== true;
    }

    protected function words(?CarbonInterface $at): string
    {
        return $at === null ? 'a while' : $at->diffForHumans(syntax: CarbonInterface::DIFF_ABSOLUTE);
    }

    protected function quietFor(): ?string
    {
        $last = Party::query()->max('playback_received_at');
        $played = PlayedSong::query()->max('played_at');
        $latest = collect([$last, $played])->filter()->map(fn ($at) => now()->parse($at))->max();

        return $latest === null ? null : $this->words($latest);
    }

    /**
     * @param  list<Party>  $live
     * @return list<Attention>
     */
    protected function attention(array $live): array
    {
        return array_values(array_filter([
            ...$this->stalePlayers($live),
            $this->relinks(),
            $this->failedJobs(),
            $this->aiErrors(),
            ...$this->providerProblems(),
        ]));
    }

    /**
     * @param  list<Party>  $live
     * @return list<Attention>
     */
    protected function stalePlayers(array $live): array
    {
        $items = [];
        foreach ($live as $party) {
            if (! $this->isStale($party)) {
                continue;
            }
            $player = $party->playback_source?->label() ?? 'The Player';
            $items[] = [
                'key' => "stale-{$party->code}",
                'severity' => 'error',
                'title' => "Player stale in {$party->name}",
                'detail' => "{$player} stopped reporting ".$this->words($party->playback_received_at)." ago, so Requests are not reaching Spotify. {$party->user->nickname} hosts it.",
                'action' => ['label' => 'Open Dashboard', 'url' => route('parties.dashboard', $party->code)],
            ];
        }

        return $items;
    }

    /**
     * @return Attention|null
     */
    protected function relinks(): ?array
    {
        $users = LinkedAccount::query()
            ->whereNotNull('needs_relink_at')
            ->whereHas('provider', fn ($query) => $query->where('code', 'spotify'))
            ->with('user')
            ->get()
            ->pluck('user')
            ->unique('id')
            ->values();
        if ($users->isEmpty()) {
            return null;
        }
        $hosting = Party::query()->where('active', true)->whereIn('user_id', $users->pluck('id'))->with('user')->get();
        $count = $users->count();
        $names = $this->sentence($users->map(fn (User $user): string => $user->nickname)->values()->all());
        $detail = "{$names} must sign in to Spotify again.";
        if ($hosting->isNotEmpty()) {
            $hosts = $hosting->map(fn (Party $party): string => "{$party->user->nickname} hosts {$party->name}")->implode('; ');
            $detail .= " {$hosts}, which cannot start playback until then.";
        }

        return [
            'key' => 'relink',
            'severity' => $hosting->isNotEmpty() ? 'error' : 'warn',
            'title' => $count === 1 ? '1 Spotify link needs re-linking' : "{$count} Spotify links need re-linking",
            'detail' => $detail,
            'action' => ['label' => 'View users', 'url' => route('admin.users.index', ['spotify_relink' => 1])],
        ];
    }

    /**
     * @return Attention|null
     */
    protected function failedJobs(): ?array
    {
        $failed = DB::table('failed_jobs')->where('failed_at', '>=', now()->subHour())->orderByDesc('failed_at')->get(['payload', 'failed_at']);
        if ($failed->isEmpty()) {
            return null;
        }
        $count = $failed->count();
        $names = $failed->map(fn (object $job): string => $this->jobName((string) $job->payload))->countBy()->sortDesc();
        $top = (string) $names->keys()->first();
        $latest = now()->parse($failed->first()->failed_at);

        return [
            'key' => 'failed-jobs',
            'severity' => 'warn',
            'title' => $count === 1 ? '1 job failed in the last hour' : "{$count} jobs failed in the last hour",
            'detail' => ($names->count() === 1 ? ($count === 1 ? "It is {$top}." : "All {$count} are {$top}.") : "Most are {$top}.")." Last one {$latest->diffForHumans()}.",
            'action' => ['label' => 'Open Horizon', 'url' => route('horizon.index', ['view' => 'failed'])],
        ];
    }

    protected function jobName(string $payload): string
    {
        $decoded = json_decode($payload, true);
        $name = is_array($decoded) && is_string($decoded['displayName'] ?? null) ? $decoded['displayName'] : 'unknown jobs';

        return class_basename($name);
    }

    /**
     * @return Attention|null
     */
    protected function aiErrors(): ?array
    {
        $errors = AiReview::query()->where('outcome', AiReviewOutcome::Error)->where('created_at', '>=', now()->subHour());
        $count = (clone $errors)->count();
        $last = $errors->with('party')->latest('created_at')->latest('id')->first();
        if ($last === null) {
            return null;
        }

        return [
            'key' => 'ai-errors',
            'severity' => 'warn',
            'title' => $count === 1 ? '1 AI review let a Request through by mistake' : "{$count} AI reviews let a Request through by mistake",
            'detail' => 'The review failed, so the Requests were allowed without a review. Last one '.$last->created_at?->diffForHumans()." in {$last->party->name}.",
            'action' => ['label' => 'See reviews', 'url' => route('parties.ai-reviews.index', $last->party->code)],
        ];
    }

    /**
     * @return list<Attention>
     */
    protected function providerProblems(): array
    {
        $items = [];
        foreach (SocialProvider::query()->where('enabled', true)->with('settings')->orderBy('name')->get() as $provider) {
            $missing = $this->health->missingSettings($provider);
            if ($missing === []) {
                continue;
            }
            $items[] = [
                'key' => "provider-{$provider->code}",
                'severity' => 'warn',
                'title' => "{$provider->name} sign-in is on but has no ".$this->sentence($missing),
                'detail' => $provider->auth_enabled
                    ? "Guests who pick {$provider->name} on the login page will see an error."
                    : "Linking a {$provider->name} account will fail.",
                'action' => ['label' => 'Fix provider', 'url' => route('admin.settings.socialproviders.edit', $provider)],
            ];
        }

        return $items;
    }

    /**
     * @return array{stats: array<string, int>, recent: list<array{icon: string, text: string, at: string}>}
     */
    protected function activity(): array
    {
        $week = now()->subWeek();

        return [
            'stats' => [
                'signUpsThisWeek' => User::query()->where('created_at', '>=', $week)->count(),
                'partiesThisWeek' => Party::query()->where('created_at', '>=', $week)->count(),
                'playedToday' => PlayedSong::query()->where('played_at', '>=', now()->startOfDay())->count(),
                'users' => User::query()->count(),
            ],
            'recent' => $this->recent(),
        ];
    }

    /**
     * @return list<array{icon: string, text: string, at: string}>
     */
    protected function recent(): array
    {
        $events = collect();
        foreach (User::query()->with('accounts.provider')->latest('created_at')->limit(self::RECENT_SIZE)->get() as $user) {
            $provider = $user->accounts->first()?->provider?->name;
            $events->push(['icon' => 'ti-user-plus', 'text' => "{$user->nickname} signed up".($provider === null ? '.' : " with {$provider}."), 'at' => $user->created_at]);
        }
        foreach (Party::query()->with('user')->latest('created_at')->limit(self::RECENT_SIZE)->get() as $party) {
            $events->push(['icon' => 'ti-playlist-add', 'text' => "{$party->user->nickname} created {$party->name}.", 'at' => $party->created_at]);
        }
        foreach (AiReview::query()->with('party')->where('outcome', AiReviewOutcome::Flag)->latest('created_at')->limit(self::RECENT_SIZE)->get() as $review) {
            $events->push(['icon' => 'ti-flag', 'text' => "AI review flagged {$review->track_title} in {$review->party->name}.", 'at' => $review->created_at]);
        }

        return array_values($events
            ->filter(fn (array $event): bool => $event['at'] !== null)
            ->sortByDesc('at')
            ->take(self::RECENT_SIZE)
            ->map(fn (array $event): array => ['icon' => $event['icon'], 'text' => $event['text'], 'at' => $event['at']->toIso8601String()])
            ->all());
    }

    /**
     * @param  array<int, string>  $items
     */
    protected function sentence(array $items): string
    {
        $last = array_pop($items);

        return $items === [] ? (string) $last : implode(', ', $items).' and '.$last;
    }
}
