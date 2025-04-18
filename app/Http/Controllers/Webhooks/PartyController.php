<?php
namespace App\Http\Controllers\Webhooks;

use App\Http\Requests\Webhooks\PartyLibrespotRequest;
use App\Jobs\PartyUpdate;
use App\Models\Party;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;

class PartyController extends Controller
{
    public function librespot(PartyLibrespotRequest $request, Party $party)
    {
        switch ($request->input('event')) {
            case 'started':
            case 'playing':
            case 'paused':
            case 'stopped':
                Log::debug("{$party}: Received librespot webhook event: {$request->input('event')}");
                PartyUpdate::dispatch($party)->afterResponse();
                break;

            default:
                break;
        }
        return response()->noContent();
    }

    public function simple(Party $party)
    {
        Log::debug("{$party}: Received simple webhook event");
        PartyUpdate::dispatch($party)->afterResponse();
        return response()->noContent();
    }
}
