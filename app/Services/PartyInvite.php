<?php

namespace App\Services;

use App\Models\Party;
use App\Models\UpcomingSong;
use App\Models\Vote;
use Illuminate\Http\Request;

class PartyInvite
{
    private const int ONLINE_MINUTES = 15;

    public function __construct(private readonly PartyListing $listing) {}

    /**
     * @return ?array{code: string, name: string, host: string, online: int, playback: string, detail: ?string, cover: ?string}
     */
    public function fromIntended(Request $request): ?array
    {
        $intended = $request->session()->get('url.intended');
        $path = is_string($intended) ? parse_url($intended, PHP_URL_PATH) : null;
        if (! is_string($path) || ! preg_match('#^/parties/([A-Za-z]{4})/?$#', $path, $matches)) {
            return null;
        }

        $party = Party::query()->with(['user', 'song.album'])->where('code', $matches[1])->first();
        if ($party === null) {
            return null;
        }

        return [
            'code' => $party->code,
            'name' => $party->name,
            'host' => $party->user->nickname,
            'online' => $this->onlineCount($party),
            ...$this->listing->playback($party),
        ];
    }

    /**
     * Guests online: the party's unbanned members who voted on, or requested, a
     * song in this party within the last 15 minutes. The app has no presence
     * channel, so recent activity is the only stored signal.
     */
    private function onlineCount(Party $party): int
    {
        $since = now()->subMinutes(self::ONLINE_MINUTES);
        $memberIds = $party->members()->whereHas('role', fn ($roles) => $roles->where('code', '!=', 'banned'))->pluck('user_id');

        $voters = Vote::query()
            ->whereIn('user_id', $memberIds)
            ->where('updated_at', '>=', $since)
            ->whereHas('upcomingSong', fn ($songs) => $songs->where('party_id', $party->id))
            ->pluck('user_id');
        $requesters = UpcomingSong::query()
            ->where('party_id', $party->id)
            ->whereIn('user_id', $memberIds)
            ->where('created_at', '>=', $since)
            ->pluck('user_id');

        return $voters->merge($requesters)->unique()->count();
    }
}
