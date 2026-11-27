<?php

namespace App\Services\Dashboard;

use App\Models\Party;
use App\Services\Music\Data\PlaybackSource;
use App\Services\Music\MusicProviderFactory;
use App\Services\Playback\EncoreConnection;
use App\Services\Playback\StartPlayback;
use App\Services\Requests\FlaggedRequests;
use Illuminate\Support\Facades\Cache;

class PartyAttention
{
    private const int FLAGGED_SIZE = 5;

    public function __construct(
        private readonly MusicProviderFactory $providers,
        private readonly EncoreConnection $encore,
        private readonly FlaggedRequests $flagged,
    ) {}

    /**
     * @return array{issues: list<array<string, mixed>>, flagged: list<array<string, mixed>>, flaggedTotal: int}
     */
    public function attention(Party $party): array
    {
        $flagged = $this->flagged->listFor($party);

        return [
            'issues' => $this->issues($party),
            'flagged' => array_slice($flagged, 0, self::FLAGGED_SIZE),
            'flaggedTotal' => count($flagged),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function issues(Party $party): array
    {
        $host = $party->user->nickname;
        $issues = [];

        if ($this->providers->partyNeedsRelink($party)) {
            $issues[] = ['code' => 'relink_required', 'fact' => 'Spotify link expired', 'detail' => "Ask {$host} to re-link", 'action' => null];
        }
        if (! $party->active) {
            $issues[] = ['code' => 'party_inactive', 'fact' => 'Party inactive', 'detail' => 'Nothing is sent to Spotify', 'action' => ['label' => 'Activate', 'kind' => 'activate']];
        }
        $encoreConnected = $party->playback_source === PlaybackSource::Encore && $this->encore->isConnected($party) === true;
        if ($party->playback_source?->isPushed() === true && ! $party->hasFreshPushedPlayback() && ! $encoreConnected) {
            $issues[] = ['code' => 'player_stale', 'fact' => 'Player not reporting', 'detail' => $party->poll ? 'Polling is checking Spotify' : 'Polling is off', 'action' => null];
        }
        if (Cache::get(StartPlayback::premiumFlagKey($party)) === true) {
            $issues[] = ['code' => 'premium_required', 'fact' => 'Spotify Premium required', 'detail' => "Ask {$host} to upgrade", 'action' => null];
        }

        return $issues;
    }
}
