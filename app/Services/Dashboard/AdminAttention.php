<?php

namespace App\Services\Dashboard;

use App\Enums\AiReviewOutcome;
use App\Models\AiReview;
use App\Models\LinkedAccount;
use App\Models\Party;
use App\Models\SocialProvider;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-type Action array{label: string, url: string}
 * @phpstan-type Attention array{key: string, severity: 'error'|'warn', title: string, detail: string, action: Action}
 */
class AdminAttention
{
    public function __construct(
        private readonly SystemHealth $health,
        private readonly PlayerStaleness $staleness,
    ) {}

    /**
     * @param  list<Party>  $live
     * @return list<Attention>
     */
    public function items(array $live): array
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
            if (! $this->staleness->isStale($party)) {
                continue;
            }
            $player = $party->playback_source?->label() ?? 'The Player';
            $items[] = [
                'key' => "stale-{$party->code}",
                'severity' => 'error',
                'title' => "Player stale in {$party->name}",
                'detail' => "{$player} stopped reporting ".$this->staleness->words($party->playback_received_at)." ago, so Requests are not reaching Spotify. {$party->user->nickname} hosts it.",
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
     * @param  array<int, string>  $items
     */
    protected function sentence(array $items): string
    {
        $last = array_pop($items);

        return $items === [] ? (string) $last : implode(', ', $items).' and '.$last;
    }
}
