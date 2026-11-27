<?php

namespace App\Services;

use App\Models\Party;
use Illuminate\Http\Request;

class PartyInvite
{
    public function __construct(private readonly PartyListing $listing) {}

    /**
     * @return ?array{code: string, name: string, host: string, guests: int, playback: string, detail: ?string, cover: ?string}
     */
    public function fromIntended(Request $request): ?array
    {
        $intended = $request->session()->get('url.intended');
        $path = is_string($intended) ? parse_url($intended, PHP_URL_PATH) : null;
        if (! is_string($path) || ! preg_match('#^/parties/([A-Za-z]{4})/?$#', $path, $matches)) {
            return null;
        }

        $party = Party::query()->with(['user', 'song.album'])->withCount('members')->where('code', $matches[1])->first();
        if ($party === null) {
            return null;
        }

        return [
            'code' => $party->code,
            'name' => $party->name,
            'host' => $party->user->nickname,
            'guests' => $party->members_count,
            ...$this->listing->playback($party),
        ];
    }
}
