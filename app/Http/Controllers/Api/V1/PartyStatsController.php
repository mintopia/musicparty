<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Party\Models\Party;
use App\Domain\Stats\Actions\GetPartyStats;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PartyStatsController extends Controller
{
    public function show(GetPartyStats $getStats, Party $party): JsonResponse
    {
        $this->authorize('viewMembers', $party);

        return response()->json(['data' => $getStats($party)]);
    }
}
