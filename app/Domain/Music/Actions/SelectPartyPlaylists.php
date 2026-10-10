<?php

namespace App\Domain\Music\Actions;

use App\Domain\Music\Capability;
use App\Domain\Music\Contracts\MusicProvider;
use App\Domain\Music\Exceptions\NotHostException;
use App\Models\Party;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class SelectPartyPlaylists
{
    public function __construct(private readonly ListHostPlaylists $listHostPlaylists) {}

    /**
     * @throws NotHostException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Party $party, MusicProvider $provider, ?string $fallbackPlaylistId, ?string $historyPlaylistId): Party
    {
        $playlists = $this->listHostPlaylists->__invoke($actor, $party, $provider);
        $known = [];
        foreach ($playlists as $playlist) {
            $known[$playlist->id] = $playlist->name;
        }

        $errors = [];

        if ($fallbackPlaylistId !== null && ! array_key_exists($fallbackPlaylistId, $known)) {
            $errors['fallback_playlist_id'] = 'The selected fallback playlist does not belong to the Host.';
        }

        if ($historyPlaylistId !== null && ! array_key_exists($historyPlaylistId, $known)) {
            $errors['history_playlist_id'] = 'The selected history playlist does not belong to the Host.';
        } elseif ($historyPlaylistId !== null && ! $provider->supports(Capability::PlaylistWrite)) {
            $errors['history_playlist_id'] = 'This Music Provider cannot write to playlists.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $party->backup_playlist_id = $fallbackPlaylistId;
        $party->backup_playlist_name = $fallbackPlaylistId !== null ? $known[$fallbackPlaylistId] : null;
        $party->history_playlist_id = $historyPlaylistId;
        $party->save();

        return $party;
    }
}
