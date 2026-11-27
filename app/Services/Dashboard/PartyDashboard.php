<?php

namespace App\Services\Dashboard;

use App\Models\Party;
use App\Models\User;
use App\Services\PartyTimezone;
use Closure;

class PartyDashboard
{
    public function __construct(
        private readonly PartyTimezone $timezones,
        private readonly PartyPlayerControl $playerControl,
        private readonly PartyRequestQueue $queue,
        private readonly PartyAttention $attention,
    ) {}

    /**
     * @return array<string, Closure(): mixed>
     */
    public function props(Party $party, User $viewer): array
    {
        $party->loadMissing('user');

        return [
            'party' => fn (): array => [
                'code' => $party->code,
                'name' => $party->name,
                'active' => (bool) $party->active,
                'weighted' => (bool) $party->weighted,
                'downvotes' => (bool) $party->downvotes,
                'canManage' => $party->canBeManagedBy($viewer),
                'canModerate' => $viewer->can('moderate', $party),
                'ownerName' => $party->user->nickname,
            ],
            'host' => fn (): array => ['name' => $party->user->nickname],
            'can' => fn (): array => [
                'control' => $viewer->can('moderate', $party),
                'moderate' => $viewer->can('moderate', $party),
                'player' => $viewer->can('player', $party),
            ],
            'timezone' => fn (): string => $this->timezones->for($party),
            'playerState' => $party->getState(...),
            'myRating' => fn (): ?array => $party->ratingBy($viewer),
            'control' => fn (): array => $this->playerControl->control($party),
            'upNext' => fn (): array => $this->queue->upNext($party, $viewer),
            'upcoming' => fn (): array => $this->queue->upcoming($party, $viewer),
            'attention' => fn (): array => $this->attention->attention($party),
            'links' => fn (): array => $this->links($party, $viewer),
            'hasFallbackPlaylist' => fn (): bool => $party->backup_playlist_id !== null,
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function links(Party $party, User $viewer): array
    {
        $settings = route('parties.edit', $party->code);

        return [
            'queue' => route('parties.show', $party->code),
            'songs' => route('parties.songs.index', $party->code),
            'flagged' => route('parties.flagged.index', $party->code),
            'settings' => $settings,
            'members' => route('parties.users.index', $party->code),
            'moderation' => route('parties.moderation.index', $party->code),
            'mods' => route('parties.mods.edit', $party->code),
            'tv' => route('parties.tv', $party->code),
            'webPlayer' => $viewer->can('player', $party) ? route('parties.player', $party->code) : null,
            'encoreRelay' => $settings.'#encore-relay',
            'relink' => route('linkedaccounts.create', 'spotify'),
            'join' => route('parties.show', $party->code),
            'fallbackSettings' => $settings,
        ];
    }
}
