<?php

namespace App\Services;

use App\Models\Party;
use App\Models\Song;
use App\Models\User;
use Illuminate\Support\Collection;

class PartyListing
{
    private const int PLAYER_STALE_MINUTES = 15;

    public function __construct(private readonly PlaybackStatus $status) {}

    /**
     * @return Collection<int, array{code: string, name: string, role: string, roleCode: string, playback: string, detail: ?string, cover: ?string, canManage: bool, canModerate: bool, url: string}>
     */
    public function forMember(User $user): Collection
    {
        $isAdmin = $user->hasRole('admin');

        return Party::query()
            ->where(fn ($query) => $query
                ->where('user_id', $user->id)
                ->orWhereHas('members', fn ($members) => $members->where('user_id', $user->id)))
            ->with(['song.album', 'members' => fn ($members) => $members->where('user_id', $user->id)->with('role')])
            ->orderBy('name')
            ->get()
            ->map(function (Party $party) use ($user, $isAdmin) {
                $canManage = $isAdmin || $party->canBeManagedBy($user);
                $canModerate = $isAdmin || $party->canBeModeratedBy($user);
                $member = $party->members->first();
                $isOwner = $party->user_id === $user->id;

                return [
                    'code' => $party->code,
                    'name' => $party->name,
                    'role' => $isOwner ? 'Owner' : ($member?->role->name ?? 'User'),
                    'roleCode' => $isOwner ? 'owner' : ($member?->role->code ?? 'user'),
                    ...$this->playback($party),
                    'canManage' => $canManage,
                    'canModerate' => $canModerate,
                    'url' => route($canModerate ? 'parties.dashboard' : 'parties.show', $party->code),
                ];
            })
            ->values();
    }

    /**
     * @return array{playback: string, detail: ?string, cover: ?string}
     */
    public function playback(Party $party): array
    {
        $state = $this->status->recall($party);
        $sampledAt = $state?->sampledAt;
        $related = $party->getRelation('song');
        $song = $related instanceof Song ? $related : null;
        [$playback, $detail] = match (true) {
            $sampledAt !== null && $sampledAt->lt(now()->subMinutes(self::PLAYER_STALE_MINUTES)) => ['stale', 'last update '.$sampledAt->diffForHumans()],
            $state === null || $song === null || $state->trackId !== $song->provider_track_id => ['idle', $party->backup_playlist_id !== null ? 'Fallback Playlist is next' : null],
            $state->isPlaying => ['playing', $song->name],
            default => ['paused', $song->name],
        };

        return [
            'playback' => $playback,
            'detail' => $detail,
            'cover' => $song?->album->image_url ?: null,
        ];
    }
}
