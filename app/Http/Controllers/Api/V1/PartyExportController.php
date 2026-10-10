<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Queue\Exceptions\RequestRefusedException;
use App\Domain\Stats\Actions\BuildPartyExport;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PartyExportResource;
use App\Models\Party;
use Illuminate\Http\JsonResponse;

class PartyExportController extends Controller
{
    public function show(BuildPartyExport $buildExport, Party $party): PartyExportResource|JsonResponse
    {
        try {
            return new PartyExportResource($buildExport($party));
        } catch (RequestRefusedException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status());
        }
    }
}
