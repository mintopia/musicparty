<?php

namespace App\Http\Controllers;

use App\Models\Party;
use App\Services\Spotify\SpotifyGateway;
use App\Services\Spotify\SpotifyRelinkRequiredException;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class WebPlayerController extends Controller
{
    public function show(Party $party): Response
    {
        return Inertia::render('Parties/WebPlayer', [
            'party' => ['code' => $party->code, 'name' => $party->name],
            'tokenUrl' => route('parties.player.token', $party),
            'deviceName' => "Music Party {$party->code}",
        ]);
    }

    public function token(Party $party, SpotifyGateway $gateway): JsonResponse
    {
        try {
            $token = $gateway->accessToken($party->user);
        } catch (SpotifyRelinkRequiredException) {
            return response()->json(['message' => 'Spotify needs re-linking.'], 409);
        }

        if ($token === null) {
            return response()->json(['message' => 'No linked Spotify account.'], 404);
        }

        return response()->json(['accessToken' => $token])->header('Cache-Control', 'no-store, private');
    }
}
