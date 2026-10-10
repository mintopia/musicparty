<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Models\User;
use App\Domain\Party\Models\Party;
use App\Domain\Playback\Actions\ClaimBrowserPlayer;
use App\Domain\Playback\Actions\FetchBrowserPlayerToken;
use App\Domain\Playback\Actions\ReleaseBrowserPlayer;
use App\Domain\Playback\Actions\ReportBrowserPlayerState;
use App\Domain\Playback\Exceptions\PlayerDisconnectedException;
use App\Domain\Playback\PlaybackStatus;
use App\Http\Requests\BrowserPlayerTabRequest;
use App\Http\Requests\ReportBrowserPlayerStateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BrowserPlayerController extends Controller
{
    public function show(Request $request, FetchBrowserPlayerToken $fetchToken, Party $party): Response
    {
        $this->authoriseHost($request, $party);

        ['accessToken' => $accessToken, 'error' => $error] = $fetchToken($this->host($request), $party);

        return Inertia::render('Party/BrowserPlayer', [
            'party' => ['code' => $party->code, 'name' => $party->name],
            'accessToken' => $accessToken,
            'error' => $error,
            'leadSeconds' => (int) config('musicparty.just_in_time_lead_seconds'),
            'channel' => 'party.'.$party->code.'.browser-player',
        ]);
    }

    public function claim(BrowserPlayerTabRequest $request, ClaimBrowserPlayer $claim, Party $party): JsonResponse
    {
        $this->authoriseHost($request, $party);

        try {
            $held = $claim($party, $request->string('tab_id')->toString());
        } catch (PlayerDisconnectedException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return $held
            ? response()->json(['claimed' => true])
            : response()->json(['message' => 'Another tab is already the player for this Party.'], 409);
    }

    public function report(ReportBrowserPlayerStateRequest $request, ReportBrowserPlayerState $report, Party $party): JsonResponse
    {
        $this->authoriseHost($request, $party);

        try {
            $accepted = $report(
                $party,
                $request->string('tab_id')->toString(),
                PlaybackStatus::from($request->string('status')->toString()),
                $request->filled('track_id') ? $request->string('track_id')->toString() : null,
                $request->integer('position_ms'),
                $request->filled('duration_ms') ? $request->integer('duration_ms') : null,
            );
        } catch (PlayerDisconnectedException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return $accepted
            ? response()->json(['accepted' => true])
            : response()->json(['message' => 'This tab does not hold the player role.'], 409);
    }

    public function release(BrowserPlayerTabRequest $request, ReleaseBrowserPlayer $release, Party $party): JsonResponse
    {
        $this->authoriseHost($request, $party);

        try {
            $released = $release($party, $request->string('tab_id')->toString(), $this->host($request));
        } catch (PlayerDisconnectedException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['released' => $released]);
    }

    private function authoriseHost(Request $request, Party $party): void
    {
        abort_unless($party->canBeManagedBy($this->host($request)), 403);
    }

    private function host(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
