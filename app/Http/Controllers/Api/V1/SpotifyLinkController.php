<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Music\Actions\AuthorisesSpotifyLinking;
use App\Domain\Music\Actions\SpotifyControlLinkStatus;
use App\Domain\Music\Actions\UnlinkHostAccount;
use App\Domain\Music\Exceptions\NotHostException;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\SpotifyLinkStatusResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SpotifyLinkController extends Controller
{
    public function show(Request $request, AuthorisesSpotifyLinking $authorises, SpotifyControlLinkStatus $status): SpotifyLinkStatusResource
    {
        $user = $this->authorisedUser($request, $authorises);

        return new SpotifyLinkStatusResource($status($user));
    }

    public function destroy(Request $request, AuthorisesSpotifyLinking $authorises, UnlinkHostAccount $unlink): Response
    {
        $unlink($this->authorisedUser($request, $authorises));

        return response()->noContent();
    }

    private function authorisedUser(Request $request, AuthorisesSpotifyLinking $authorises): User
    {
        $user = $request->user();
        $user instanceof User || abort(401);

        try {
            $authorises($user);
        } catch (NotHostException $exception) {
            abort(403, $exception->getMessage());
        }

        return $user;
    }
}
