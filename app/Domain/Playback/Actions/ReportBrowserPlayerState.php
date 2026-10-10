<?php

namespace App\Domain\Playback\Actions;

use App\Domain\Playback\Data\PlaybackState;
use App\Domain\Playback\Data\TrackReference;
use App\Domain\Playback\Exceptions\PlayerDisconnectedException;
use App\Domain\Playback\PlaybackCoordinator;
use App\Domain\Playback\PlaybackStatus;
use App\Models\Party;
use Carbon\CarbonImmutable;

readonly class ReportBrowserPlayerState
{
    public function __construct(private ResolveBrowserPlayer $resolve, private PlaybackCoordinator $coordinator) {}

    /**
     * @throws PlayerDisconnectedException
     */
    public function __invoke(Party $party, string $tabId, PlaybackStatus $status, ?string $trackId, int $positionMs, ?int $durationMs): bool
    {
        $player = ($this->resolve)($party);
        $previousTrack = $player->state()->currentTrack?->providerTrackId;

        $state = new PlaybackState(
            $status,
            $trackId === null ? null : new TrackReference($party->music_provider, $trackId),
            $positionMs,
            CarbonImmutable::now(),
            $durationMs,
        );

        if (! $player->remember($tabId, $state)) {
            return false;
        }

        if ($trackId !== null && $trackId !== $previousTrack) {
            $this->coordinator->trackChanged($party, $trackId);
        } elseif ($status === PlaybackStatus::Stopped) {
            $this->coordinator->playbackEnded($party);
        }

        return true;
    }
}
