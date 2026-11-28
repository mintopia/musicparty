<?php

namespace App\Http\Controllers;

use App\Models\Party;
use App\Services\Music\Data\PlaybackSource;
use App\Services\PlaybackControl;
use App\Services\Spotify\SpotifyGateway;
use App\Services\Spotify\SpotifyRelinkRequiredException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class WebPlayerController extends Controller
{
    public function __construct(protected PlaybackControl $playback) {}

    public function show(Party $party): Response
    {
        return Inertia::render('Parties/WebPlayer', [
            'party' => ['code' => $party->code, 'name' => $party->name],
            'tokenUrl' => route('parties.player.token', $party),
            'activateUrl' => route('parties.player.activate', $party),
            'deviceName' => "Music Party {$party->code}",
        ]);
    }

    public function activate(Party $party, Request $request): JsonResponse
    {
        $deviceId = $request->validate([
            'device_id' => ['required', 'string'],
        ])['device_id'];

        try {
            $this->playback->perform($party, 'play', $deviceId);
        } catch (SpotifyRelinkRequiredException) {
            return response()->json(['message' => 'Spotify needs re-linking.'], 409);
        } catch (Throwable $e) {
            return $this->spotifyUnavailable($party, $e);
        }

        $party->device_id = $deviceId;
        $party->device_name = "Music Party {$party->code}";
        $party->playback_source = PlaybackSource::WebPlayer;
        $party->playback_received_at = now();
        $party->save();

        return response()->json(['status' => 'ok'])->header('Cache-Control', 'no-store, private');
    }

    public function token(Party $party, SpotifyGateway $gateway): JsonResponse
    {
        try {
            $token = $gateway->accessToken($party->user);
        } catch (SpotifyRelinkRequiredException) {
            return response()->json(['message' => 'Spotify needs re-linking.'], 409);
        } catch (Throwable $e) {
            return $this->spotifyUnavailable($party, $e);
        }

        if ($token === null) {
            return response()->json(['message' => 'No linked Spotify account.'], 404);
        }

        return response()->json(['accessToken' => $token])->header('Cache-Control', 'no-store, private');
    }

    private function spotifyUnavailable(Party $party, Throwable $e): JsonResponse
    {
        Log::warning("Web player Spotify call failed for party {$party->id}: {$e->getMessage()}");

        return response()->json(['message' => 'Spotify is unavailable, try again shortly.'], 503);
    }
}
