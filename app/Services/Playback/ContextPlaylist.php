<?php

namespace App\Services\Playback;

use App\Enums\Purpose;
use App\Models\Party;
use App\Services\Contracts\MusicProvider;
use App\Services\Music\MusicProviderFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ContextPlaylist
{
    public const string NAME = 'Music Party Context';

    public const string DESCRIPTION = 'Used by Music Party to start playback. Leave it empty.';

    private const int VERIFIED_SECONDS = 600;

    public function __construct(protected MusicProviderFactory $providers) {}

    public function ensure(Party $party, MusicProvider $provider): string
    {
        $stored = $party->context_playlist_id;
        if ($stored !== null && (Cache::get($this->verifiedKey($party)) === $stored || $provider->ownedPlaylist($stored) !== null)) {
            Cache::put($this->verifiedKey($party), $stored, self::VERIFIED_SECONDS);

            return $stored;
        }

        $playlist = $provider->findOwnedPlaylistByName(self::NAME) ?? $provider->createPrivatePlaylist(self::NAME, self::DESCRIPTION);
        $party->forceFill(['context_playlist_id' => $playlist->id])->saveQuietly();
        Cache::put($this->verifiedKey($party), $playlist->id, self::VERIFIED_SECONDS);

        return $playlist->id;
    }

    public function enforceEmpty(Party $party): void
    {
        $id = $party->context_playlist_id;
        if ($id === null) {
            return;
        }

        $provider = $this->providers->forParty($party, Purpose::Control);
        $playlist = $provider->ownedPlaylist($id);
        if ($playlist === null) {
            $party->forceFill(['context_playlist_id' => null])->saveQuietly();
            Cache::forget($this->verifiedKey($party));

            return;
        }

        if ($playlist->trackCount > 0) {
            Log::info("{$party}: Emptying context playlist {$id} ({$playlist->trackCount} tracks)");
            $provider->clearPlaylist($id);
        }
    }

    public function isPartyContext(Party $party, MusicProvider $provider, ?string $contextUri): bool
    {
        return $contextUri !== null
            && $party->context_playlist_id !== null
            && $contextUri === $provider->playlistContextUri($party->context_playlist_id);
    }

    public function forgetVerified(Party $party): void
    {
        Cache::forget($this->verifiedKey($party));
    }

    protected function verifiedKey(Party $party): string
    {
        return "party.{$party->id}.context-verified";
    }
}
