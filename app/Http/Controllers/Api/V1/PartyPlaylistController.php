<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Music\Actions\ListHostPlaylists;
use App\Domain\Music\Actions\SelectPartyPlaylists;
use App\Domain\Music\Exceptions\NotHostException;
use App\Domain\Music\Exceptions\ProviderTemporaryFailure;
use App\Domain\Party\Models\Party;
use App\Domain\Party\PairingCatalogue;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PartyPlaylistRequest;
use App\Http\Resources\V1\PartyPlaylistSelectionResource;
use App\Http\Resources\V1\PlaylistResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PartyPlaylistController extends Controller
{
    public function index(Request $request, Party $party, ListHostPlaylists $listHostPlaylists, PairingCatalogue $catalogue): AnonymousResourceCollection
    {
        try {
            $playlists = $listHostPlaylists($request->user() ?? abort(401), $party, $catalogue->provider($party->music_provider));
        } catch (NotHostException $exception) {
            abort(403, $exception->getMessage());
        } catch (ProviderTemporaryFailure $exception) {
            abort(503, $exception->getMessage());
        }

        return PlaylistResource::collection($playlists);
    }

    public function update(PartyPlaylistRequest $request, Party $party, SelectPartyPlaylists $selectPartyPlaylists, PairingCatalogue $catalogue): PartyPlaylistSelectionResource
    {
        try {
            $party = $selectPartyPlaylists(
                $request->user() ?? abort(401),
                $party,
                $catalogue->provider($party->music_provider),
                $request->validated('fallback_playlist_id'),
                $request->validated('history_playlist_id'),
            );
        } catch (NotHostException $exception) {
            abort(403, $exception->getMessage());
        } catch (ProviderTemporaryFailure $exception) {
            abort(503, $exception->getMessage());
        }

        return new PartyPlaylistSelectionResource($party);
    }
}
