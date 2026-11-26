<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Party;
use App\Models\UpcomingSong;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PlaybackController extends Controller
{
    public function start(Request $request, Party $party): JsonResponse
    {
        $this->authorize('update', $party);

        return response()->json(['message' => 'Not implemented'], 501);
    }

    public function devices(Request $request, Party $party): JsonResponse
    {
        $this->authorize('update', $party);

        return response()->json(['message' => 'Not implemented'], 501);
    }

    public function activate(Request $request, Party $party): Response
    {
        $this->authorize('update', $party);

        return response()->noContent();
    }

    public function playNow(Request $request, Party $party, UpcomingSong $upcomingsong): Response
    {
        $this->authorize('update', $party);
        $this->authorize('update', $upcomingsong);

        return response()->noContent();
    }
}
