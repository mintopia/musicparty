<?php

namespace App\Services\Dashboard;

use App\Enums\AiReviewOutcome;
use App\Models\AiReview;
use App\Models\Party;
use App\Models\PlayedSong;
use App\Models\User;

class AdminActivity
{
    private const int RECENT_SIZE = 4;

    public function __construct(private readonly PlayerStaleness $staleness) {}

    /**
     * @return array{stats: array<string, int>, recent: list<array{icon: string, text: string, at: string}>}
     */
    public function activity(): array
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
    private function recent(): array
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

    public function quietFor(): ?string
    {
        $last = Party::query()->max('playback_received_at');
        $played = PlayedSong::query()->max('played_at');
        $latest = collect([$last, $played])->filter()->map(fn ($at) => now()->parse($at))->max();

        return $latest === null ? null : $this->staleness->words($latest);
    }
}
