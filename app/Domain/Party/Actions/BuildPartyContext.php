<?php

declare(strict_types=1);

namespace App\Domain\Party\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Party\Models\Party;
use App\Domain\Party\PartyState;
use App\Domain\Playback\Control;
use App\Domain\Playback\PartyPlayers;
use App\Domain\Queue\Actions\RankQueue;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\Presenters\QueueEntryPresenter;
use App\Domain\Queue\RequestStatus;
use App\Domain\Theming\Actions\RenderPartyThemeCss;
use Illuminate\Support\Facades\Gate;

readonly class BuildPartyContext
{
    public function __construct(
        private RankQueue $rank,
        private QueueEntryPresenter $presenter,
        private RenderPartyThemeCss $renderPartyThemeCss,
        private PartyPlayers $players,
    ) {}

    /**
     * @return array{party: array{code: string, name: string, state: string}, nowPlaying: array<string, mixed>|null, capabilities: array{play: bool, pause: bool, skip: bool, previous: bool}, role: string, menu: list<array{key: string, label: string, href: string}>, themeCss: string}
     */
    public function handle(Party $party, ?User $user): array
    {
        $member = $user === null ? null : $party->memberFor($user);
        $active = $member !== null && ! $member->banned;
        $gate = Gate::forUser($user);

        $canManage = $user !== null && $gate->allows('update', $party);
        $canModerate = $active && $gate->allows('moderate', $party);

        return [
            'party' => [
                'code' => $party->code,
                'name' => $party->name,
                'state' => $party->state->value,
            ],
            'nowPlaying' => $this->nowPlaying($party),
            'capabilities' => $this->capabilities($party),
            'role' => $this->role($active, $canManage, $member?->role->isModerator() ?? false),
            'menu' => $active ? $this->menu($party, $gate->allows('viewLog', $party), $canModerate, $canManage) : [],
            'themeCss' => $this->renderPartyThemeCss->handle($party),
        ];
    }

    /**
     * @return array{play: bool, pause: bool, skip: bool}
     */
    private function capabilities(Party $party): array
    {
        $player = $this->players->for($party);

        return [
            'play' => $player?->supports(Control::Play) ?? false,
            'pause' => $player?->supports(Control::Pause) ?? false,
            'skip' => $player?->supports(Control::Skip) ?? false,
            'previous' => $player?->supports(Control::Previous) ?? false,
        ];
    }

    private function role(bool $active, bool $canManage, bool $moderator): string
    {
        return match (true) {
            ! $active => 'viewer',
            $canManage => 'host',
            $moderator => 'moderator',
            default => 'member',
        };
    }

    /**
     * @return list<array{key: string, label: string, href: string}>
     */
    private function menu(Party $party, bool $canViewLog, bool $canModerate, bool $canManage): array
    {
        $base = '/parties/'.$party->code;

        $items = [
            ['queue', 'Queue', $base, true],
            ['search', 'Search', $base.'/search', true],
            ['history', 'History', $base.'/history', true],
            ['party', 'Party', $base.'/party', true],
            ['members', 'Members', $base.'/members', true],
            ['pending', 'Pending', $base.'/requests/pending', $canModerate || $party->hold_requests],
            ['blocklist', 'Blocklist', $base.'/blocklist', $canModerate],
            ['log', 'Log', $base.'/log', $canViewLog],
            ['stats', 'Stats', $base.'/stats', true],
            ['mods', 'Mods', $base.'/mods', $canManage],
            ['theme', 'Theme', $base.'/theme', $canManage],
            ['player-tokens', 'Player Tokens', $base.'/player-tokens', $canManage],
            ['settings', 'Settings', $base.'/settings', $canManage],
        ];

        return array_values(array_map(
            fn (array $item): array => ['key' => $item[0], 'label' => $item[1], 'href' => $item[2]],
            array_filter($items, fn (array $item): bool => $item[3]),
        ));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function nowPlaying(Party $party): ?array
    {
        $playing = ($this->rank)($party, TrackRequest::query()
            ->where('party_id', $party->id)
            ->where('status', RequestStatus::Playing)
            ->with('requester.user', 'party')
            ->with(['play' => fn ($query) => $query->withRatingSummary()])
            ->withHasOtherVotes())->requests->first();

        if (! $playing instanceof TrackRequest) {
            return null;
        }

        $entry = ($this->presenter)($playing);
        $startedAt = $playing->started_at;
        $elapsed = $startedAt === null ? 0 : max(0, (int) $startedAt->diffInMilliseconds(now(), true));

        return [
            ...$entry,
            'started_at' => $startedAt?->toIso8601String(),
            'position_ms' => min($elapsed, (int) $playing->duration_ms),
            'paused' => $party->state === PartyState::Paused,
            'net_rating' => (int) ($entry['likes'] ?? 0) - (int) ($entry['dislikes'] ?? 0),
        ];
    }
}
